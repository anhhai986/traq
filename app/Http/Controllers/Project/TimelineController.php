<?php
/*!
 * Traq
 * Copyright (C) 2009-2016 Jack P.
 * Copyright (C) 2012-2016 Traq.io
 * https://github.com/nirix
 * https://traq.io
 *
 * This file is part of Traq.
 *
 * Traq is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 3 only.
 *
 * Traq is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Traq. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Traq\Controllers;

use DateTime;
use Avalon\Http\Request;
use Avalon\Helpers\Pagination;
use Traq\Helpers\Timeline as TimelineHelper;
use Traq\Models\Timeline as TimelineModel;

/**
 * Timeline controller.
 *
 * @package Traq\Controllers
 * @author Jack P.
 * @since 3.0.0
 */
class Timeline extends AppController
{
    public function __construct()
    {
        parent::__construct();
        $this->addCrumb($this->translate('timeline'), routeUrl('timeline'));
    }

    /**
     * Handles the timeline page.
     */
    public function indexAction()
    {
        $days = [];

        $filters = array_keys(TimelineHelper::filters());
        $timelineEvents = TimelineHelper::events();

        // Check if filters are set
        if (isset($_SESSION['timeline_filters'])) {
            $filters = [];
            $timelineEvents  = [];

            // Process filters
            foreach ($_SESSION['timeline_filters'] as $filter) {
                $filters[] = $filter;
                $timelineEvents = array_merge($timelineEvents, TimelineHelper::filters($filter));
            }
        }

        // Quote the events
        $timelineEvents = array_map([$this->db, 'quote'], $timelineEvents);

        // Fetch dates
        $query = queryBuilder()->select('DATE(t.created_at) AS date');
        $query->from(PREFIX . 'timeline', 't')
            ->where('t.project_id = :project_id')
            ->andWhere($query->expr()->in('t.action', $timelineEvents))
            ->orderBy('date', 'DESC')
            ->groupBy('DATE(t.created_at)')
            ->setParameter('project_id', $this->currentProject['id']);

        // Pagination
        $pagination = new Pagination(
            Request::$query->get('page', 1), // Page
            setting('timeline_days_per_page'), // Per page
            $query->execute()->rowCount()
        );

        // Limit?
        if ($pagination->paginate) {
            $query->setFirstResult($pagination->limit);
            $query->setMaxResults(setting('timeline_days_per_page'));
        }

        // Holds all the IDs from events for fetching later
        $ids = [
            'tickets'    => [],
            'milestones' => [],
            'wiki'       => []
        ];

        // Fetch events
        foreach ($query->execute()->fetchAll() as $day) {
            $events = queryBuilder()->select('t.*', 'u.name AS user_name', 'u.email AS user_email')
            ->from(PREFIX . 'timeline', 't')
            ->where('t.project_id = :project_id')
            ->andWhere($query->expr()->in('action', $timelineEvents))
            ->andWhere($query->expr()->eq('DATE(t.created_at)', $this->db->quote($day['date'])))
            ->leftJoin('t', PREFIX . 'users', 'u', 'u.id = t.user_id')
            ->orderBy('t.created_at', 'DESC')
            ->setParameter('project_id', $this->currentProject['id'])
            ->execute()
            ->fetchAll();

            $day['events'] = [];

            foreach ($events as $event) {
                if (strpos($event['action'], 'ticket_') === 0) {
                    $ids['tickets'][] = $event['owner_id'];
                } elseif (strpos($event['action'], 'milestone_') === 0) {
                    $ids['milestones'][] = $event['owner_id'];
                } elseif (strpos($event['action'], 'wiki_page') === 0) {
                    $ids['wiki'][] = $event['owner_id'];
                }

                $day['events'][] = $event;
            }

            $days[] = $day;
        }

        // Fetch needed data all at once instead of one at a time, less queries executed
        $tickets    = $this->getRows('tickets', $ids['tickets']);
        $milestones = $this->getRows('milestones', $ids['milestones']);
        $wikiPages  = $this->getRows('wiki_pages', $ids['wiki']);

        return $this->render('timeline/index.phtml', [
            'days'       => $days,
            'tickets'    => $tickets,
            'milestones' => $milestones,
            'wikiPages'  => $wikiPages,
            'filters'    => $filters,
            'pagination' => $pagination
        ]);
    }

    /**
     * Set filters.
     */
    public function setFiltersAction()
    {
        $filters = [];
        foreach (Request::$post->get('filters') as $filter => $value) {
            if ($value) {
                $filters[] = $filter;
            }
        }

        $_SESSION['timeline_filters'] = $filters;

        return $this->redirectTo('timeline', ['pslug' => $this->currentProject['slug']]);
    }

    /**
     * Delete timeline event.
     *
     * @param integer $id
     */
    public function deleteEventAction($id)
    {
        if (!$this->hasPermission('delete_timeline_events')) {
            return $this->show403();
        }

        $event = TimelineModel::select()->where('id = ?')->andWhere('project_id = ?')
            ->setParameter(0, $id)->setParameter(1, $this->currentProject['id'])
            ->fetch();

        if ($event) {
            $event->delete();
        }

        return $this->respondTo(function ($format) use ($event) {
            if ($format == 'html' || !$event) {
                return $this->redirectTo('timeline', ['pslug' => $this->currentProject['slug']]);
            } else {
                return $this->renderJs('timeline/delete_event.js.php', ['event' => $event]);
            }
        });
    }

    /**
     * Get filtered events.
     */
    protected function getFilteredEvents()
    {
        if (!isset($_SESSION['timelineFilters'])) {
            return TimelineHelper::events();
        }

        // Process filters
        $events = [];
        foreach ($_SESSION['timelineFilters'] as $filter => $value) {
            if ($value) {
                $filters[] = $filter;
                $events = array_merge($events, TimelineHelper::filters($filter));
            }
        }

        return [$filters, $events];
    }

    /**
     * Get rows from the specified table.
     *
     * @param array $ids
     *
     * @return array
     */
    protected function getRows($table, $ids)
    {
        if (!count($ids)) {
            return;
        }

        if ($table === 'tickets') {
            $query = ticketQuery();

            $query->andWhere(
                $query->expr()->in('t.id', $ids)
            );
        } else {
            $query = queryBuilder()->select('*')->from($this->db->prefix . $table);

            $query->where(
                $query->expr()->in('id', $ids)
            );
        }

        $rows = [];
        foreach ($query->execute()->fetchAll() as $row) {
            $rows[$row['id']] = $row;
        }

        return $rows;
    }
}
