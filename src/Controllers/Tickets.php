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

use Avalon\Http\Request;
use Traq\Models\Ticket;
use Traq\Models\TicketHistory;
use Traq\Models\Timeline as TimelineModel;
use Traq\Models\User;
use Traq\Models\Status;

/**
 * Ticket controller.
 *
 * @author Jack P.
 * @since 3.0.0
 */
class Tickets extends AppController
{
    public function __construct()
    {
        parent::__construct();
        $this->addCrumb($this->translate('tickets'), $this->generateUrl('tickets'));

        $this->before(['new', 'create'], function () {
            if (!$this->hasPermission('create_tickets')) {
                return $this->show403();
            }
        });

        $this->before(['editDescription', 'saveDescription'], function () {
            if (!$this->hasPermission('edit_ticket_description')) {
                return $this->show403();
            }
        });
    }

    /**
     * New ticket form.
     */
    public function newAction()
    {
        $ticket = new Ticket([
            'type_id'     => $this->currentProject['default_ticket_type_id'],
            'severity_id' => 4
        ]);

        return $this->render('tickets/new.phtml', ['ticket' => $ticket]);
    }

    /**
     * Create ticket.
     */
    public function createAction()
    {
        $ticket = new Ticket($this->ticketParams());

        if ($ticket->validate()) {
            $ticket->save();

            TimelineModel::newTicketEvent($this->currentUser, $ticket)->save();

            $this->currentProject->next_ticket_id++;
            $this->currentProject->save();

            return $this->redirectTo('ticket', [
                'pslug' => $this->currentProject['slug'],
                'id'    => $ticket->ticket_id
            ]);
        }

        return $this->render('tickets/new.phtml', ['ticket' => $ticket]);
    }

    /**
     * Handles the view ticket page.
     *
     * @param integer $ticket_id
     */
    public function showAction($id)
    {
        if (!$this->hasPermission('view_tickets')) {
            return $this->show403();
        }

        $ticket = ticketQuery()
            ->addSelect('t.*')
            ->where('t.project_id = ?')
            ->andWhere('t.ticket_id = ?')
            ->setParameter(0, $this->currentProject['id'])
            ->setParameter(1, $id)
            // ->execute()
            ->fetch();

        if (!$ticket) {
            return $this->show404();
        }

        $this->addCrumb(
            $this->translate('ticket.page-title', $ticket['ticket_id'], $ticket['summary']),
            $this->generateUrl('ticket')
        );

        $history = $ticket->history()
            ->addSelect('h.*', 'u.name AS user_name', 'u.email AS user_email')
            ->leftJoin('h', User::tableName(), 'u', 'u.id = h.user_id')
            ->orderBy('h.created_at', 'ASC')
            ->fetchAll();

        return $this->respondTo(function ($format) use ($ticket, $history) {
            if ($format == 'html') {
                return $this->render('tickets/show.phtml', [
                    'ticket'  => $ticket,
                    'history' => $history
                ]);
            } elseif ($format == 'json') {
                return $this->jsonResponse($ticket);
            }
        });
    }

    /**
     * Update ticket.
     *
     * @param integer $id id matching ticket_id
     */
    public function updateAction($id)
    {
        if (!$this->hasPermission('update_tickets')
        || !$this->hasPermission('comment_on_tickets')) {
            return $this->show403();
        }

        // Fetch the ticket, but filter it by ticket_id and project_id
        $ticket = ticketQuery()
            ->addSelect('t.*')
            // ->addSelect('t.project_id')
            ->where('t.ticket_id = ?')
            ->andWhere('t.project_id = ?')
            ->setParameter(0, $id)
            ->setParameter(1, $this->currentProject['id'])
            ->fetch();

        if ($this->hasPermission('update_tickets')) {
            $data = $this->ticketParamsUpdate();
            $changes = $this->makeChanges($ticket, $data);
        } else {
            $data = [];
            $changes = [];
        }

        if ($this->hasPermission('comment_on_tickets')) {
            $comment = empty(Request::$post->get('comment')) ? null : Request::$post->get('comment');
        }

        if (count($changes) || Request::$post->get('comment')) {
            $update = new TicketHistory([
                'user_id'   => $this->currentUser['id'],
                'ticket_id' => $ticket['id'],
                'changes'   => count($changes) ? $changes : null,
                'comment'   => isset($comment) ? $comment : null
            ]);

            $ticket->set($data);

            if ($ticket->validate()) {
                $ticket->save();
                $update->save();

                // Which action is being performed?
                $status = Status::find($ticket->status_id)->name;
                if (!count($changes)) {
                    $action = 'ticket_comment';
                    $status = null;
                } elseif ($ticket->isClosing) {
                    $action = 'ticket_closed';
                } elseif ($ticket->isReopening) {
                    $action = 'ticket_reopened';
                } else {
                    $action = 'ticket_updated';
                    $status = null;
                }

                $timeline = TimelineModel::updateTicketEvent($this->currentUser, $ticket, $action, $status);
                $timeline->save();

                return $this->redirectTo('ticket', ['pslug' => $this->currentProject['slug'], $ticket['ticket_id']]);
            } else {
                $this->set('ticketModel', $ticket);
                return $this->render('tickets/update.phtml', ['ticket' => $ticket]);
            }
        } else {
            return $this->redirectTo('ticket', ['pslug' => $this->currentProject['slug'], $ticket['ticket_id']]);
        }
    }

    /**
     * Edit ticket description form.
     *
     * @param integer $id ticket_id
     */
    public function editDescriptionAction($id)
    {
        $ticket = Ticket::select('t.ticket_id', 't.body')->where('ticket_id = ?')->andWhere('project_id = ?')
            ->setParameter(0, $id)->setParameter(1, $this->currentProject['id'])
            ->execute()
            ->fetch();

        return $this->render('tickets/edit_description.overlay.phtml', ['ticket' => $ticket]);
    }

    /**
     * Save ticket description form.
     *
     * @param integer $id ticket_id
     */
    public function saveDescriptionAction($id)
    {
        $ticket = Ticket::select()->where('ticket_id = ?')->andWhere('project_id = ?')
            ->setParameter(0, $id)->setParameter(1, $this->currentProject['id'])
            ->fetch();

        $ticket->body = Request::$post->get('body');
        $ticket->save();

        return $this->redirectTo('ticket', ['pslug' => $this->currentProject['slug'], 'id' => $ticket['ticket_id']]);
    }

    /**
     * Get params for a new ticket.
     *
     * @return array
     */
    protected function ticketParams()
    {
        $params = [
            'ticket_id'    => $this->currentProject['next_ticket_id'],
            'summary'      => Request::$post->get('summary'),
            'body'         => Request::$post->get('body'),
            'user_id'      => $this->currentUser['id'],
            'project_id'   => $this->currentProject['id'],
            'milestone_id' => 0,
            'version_id'   => 0,
            'component_id' => 0,
            'type_id'      => Request::$post->get('type_id', $this->currentProject['default_ticket_type_id']),
            'severity_id'  => 4,
            'tasks'        => []
        ];

        return $this->ticketParamsPermissionable('set', $params);
    }

    /**
     * Get params for a ticket update.
     *
     * @return array
     */
    protected function ticketParamsUpdate()
    {
        $params = [];

        if ($this->hasPermission("ticket_properties_change_summary")) {
            $params['summary'] = Request::$post->get('summary');
        }

        if ($this->hasPermission("ticket_properties_change_type")) {
            $params['type_id'] = Request::$post->get('type_id');
        }

        $params = $this->ticketParamsPermissionable('change', $params);

        $paramsToSet = [];

        foreach ($params as $key => $value) {
            if ($value != null) {
                $paramsToSet[$key] = $value;
            }
        }

        return $paramsToSet;
    }

    /**
     * Get ticket data for the field the user is allowed to set or change.
     *
     * @param string $setOrChange set or change permission type
     * @param array  $params      already existing array of params
     *
     * @return array
     */
    protected function ticketParamsPermissionable($setOrChange, array $params = [])
    {
        // Milestone
        if ($this->hasPermission("ticket_properties_{$setOrChange}_milestone")) {
            $params['milestone_id'] = Request::$post->get('milestone_id');
        }

        // Version
        if ($this->hasPermission("ticket_properties_{$setOrChange}_version")) {
            $params['version_id'] = Request::$post->get('version_id');
        }

        // Component
        if ($this->hasPermission("ticket_properties_{$setOrChange}_component")) {
            $params['component_id'] = Request::$post->get('component_id');
        }

        // Severity
        if ($this->hasPermission("ticket_properties_{$setOrChange}_severity")) {
            $params['severity_id'] = Request::$post->get('severity_id');
        }

        // Priority
        if ($this->hasPermission("ticket_properties_{$setOrChange}_priority")) {
            $params['priority_id'] = Request::$post->get('priority_id');
        }

        // Status
        if ($this->hasPermission("ticket_properties_{$setOrChange}_status")) {
            $params['status_id'] = Request::$post->get('status_id');
        }

        // Assigned to
        if ($this->hasPermission("ticket_properties_{$setOrChange}_assigned_to")) {
            $params['assigned_to_id'] = Request::$post->get('assigned_to_id');
        }

        // Tasks
        if ($this->hasPermission("ticket_properties_{$setOrChange}_tasks")) {
            $tasks = json_decode(Request::$post->get('tasks', ''), true);

            if (is_array($tasks)) {
                foreach ($tasks as $id => $task) {
                    if (is_array($task) and !empty($task['task'])) {
                        $params['tasks'][] = $task;
                    }
                }
            }
        }

        return $params;
    }

    /**
     * Make the ticket history changes array.
     *
     * @param Ticket $ticket
     * @param array  $data
     *
     * @return array
     */
    protected function makeChanges($ticket, $data)
    {
        $changes = [];

        foreach ($data as $field => $value) {
            $fieldNoId = str_replace('_id', '', $field);

            if ($value != $ticket[$field]) {
                switch ($field) {
                    case 'summary':
                        $from = $ticket[$field];
                        $to = $data[$field];
                        break;

                    case 'type_id':
                    case 'status_id':
                    case 'milestone_id':
                    case 'version_id':
                    case 'component_id':
                    case 'priority_id':
                    case 'severity_id':
                        $model = '\\Traq\\Models\\' . ucfirst($fieldNoId == 'version' ? 'milestone' : $fieldNoId);

                        $from = $ticket[$fieldNoId . '_name'];

                        if ($data[$field] == 0) {
                            $to = null;
                        } else {
                            $to = $model::find($data[$field])->name;
                        }
                        break;

                    case 'assigned_to_id':
                        $from = $ticket['assigned_to_name'];

                        if ($value == 0) {
                            $to = null;
                        } else {
                            $user = User::find($value);
                            $to = $user->name;
                        }

                        break;
                }

                $changes[] = [
                    'property' => $fieldNoId,
                    'from'     => $from,
                    'to'       => $to
                ];
            }
        }

        return $changes;
    }
}
