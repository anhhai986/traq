<?php
/*!
 * Traq
 * Copyright (C) 2009-2015 Jack P.
 * Copyright (C) 2012-2015 Traq.io
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

namespace Traq\Helpers;

/**
 * Ticket filtering.
 *
 * @package Traq\Helpers
 * @author Jack P.
 * @since 3.0.0
 */
class TicketFilterQuery
{
    protected $builder;
    protected $expr;
    public $query;
    public $filters = [];

    public function __construct($builder, $query)
    {
        $this->builder = $builder;
        $this->expr    = $builder->expr();
        $this->query   = $query;

        $this->filter();
    }

    public function filter(array $query = null)
    {
        if (!$query) {
            $query = $this->query;
        } else {
            $this->query = $query;
        }

        if (isset($query['open'])) {
            $this->allOpen();
        } elseif (isset($query['started'])) {
            $this->allStarted();
        } elseif (isset($query['closed'])) {
            $this->allClosed();
        }

        foreach (array_keys($query) as $filter) {
            $method = $filter;

            if ($filter == 'assigned_to') {
                $method = 'assignedTo';
            }

            if (method_exists(get_called_class(), $method) && !empty($query[$filter])) {
                $this->{$method}();
            } elseif (method_exists(get_called_class(), $method) && empty($query[$filter])) {
                $this->filters[$filter] = ['cond' => true, 'values' => []];
            }
        }
    }

    /**
     * Summary.
     */
    protected function summary()
    {
        $this->likeFilter('summary', 't.summary');
    }

    /**
     * Description / body.
     */
    protected function description()
    {
        $this->likeFilter('description', 't.body');
    }

    /**
     * Owner / reporter / user.
     */
    public function owner()
    {
        $info = $this->extract('owner');

        $info['values'] = explode(',', $info['values']);
        $values = array_map([$this, 'quote'], $info['values']);

        if ($info['cond']) {
            $expr = $this->expr->in('u.name', $values);
        } else {
            $expr = $this->expr->notIn('u.name', $values);
        }

        $this->builder->andWhere($expr);

        $this->filters['owner'] = $info;
    }

    /**
     * Assigned to / assignee
     */
    public function assignedTo()
    {
        $info = $this->extract('assigned_to');

        $info['values'] = explode(',', $info['values']);
        $values = array_map([$this, 'quote'], $info['values']);

        if ($info['cond']) {
            $expr = $this->expr->in('at.name', $values);
        } else {
            $expr = $this->expr->notIn('at.name', $values);
        }

        $this->builder->andWhere($expr);

        $this->filters['assigned_to'] = $info;
    }

    /**
     * Milestones.
     */
    protected function milestone()
    {
        $info = $this->extract('milestone');

        $info['values'] = explode(',', $info['values']);
        $values = array_map([$this, 'quote'], $info['values']);

        if ($info['cond']) {
            $expr = $this->expr->in('m.slug', $values);
        } else {
            $expr = $this->expr->notIn('m.slug', $values);
        }

        $this->builder->andWhere($expr);

        $this->filters['milestone'] = $info;
    }

    /**
     * Versions.
     */
    protected function version()
    {
        $info = $this->extract('version');

        $info['values'] = explode(',', $info['values']);
        $values = array_map([$this, 'quote'], $info['values']);

        if ($info['cond']) {
            $expr = $this->expr->in('v.slug', $values);
        } else {
            $expr = $this->expr->notIn('v.slug', $values);
        }

        $this->builder->andWhere($expr);

        $this->filters['version'] = $info;
    }

    /**
     * Statuses.
     */
    protected function status()
    {
        $info = $this->extract('status');

        $info['values'] = explode(',', $info['values']);
        $values = array_map([$this, 'quote'], $info['values']);

        $this->builder->andWhere(
            $this->expr->in('s.name', $values)
        );

        $this->filters['status'] = $info;
    }

    /**
     * All open statuses.
     */
    protected function allOpen()
    {
        $statuses = queryBuilder()->select('id', 'name')->from(PREFIX . 'statuses')
            ->where('status >= 1')
            ->execute();

        $ids = [];
        $names = [];
        foreach ($statuses->fetchAll() as $status) {
            $ids[] = $status['id'];
            $names[] = $status['name'];
        }

        $this->builder->andWhere(
            $this->expr->in('t.status_id', $ids)
        );

        $this->filters['status'] = ['cond' => true, 'values' => $names];
    }

    /**
     * All started statuses.
     */
    protected function allStarted()
    {
        $statuses = queryBuilder()->select('id', 'name')->from(PREFIX . 'statuses')
            ->where('status = 2')
            ->execute();

        $ids = [];
        $names = [];
        foreach ($statuses->fetchAll() as $status) {
            $ids[] = $status['id'];
            $names[] = $status['name'];
        }

        $this->builder->andWhere(
            $this->expr->in('t.status_id', $ids)
        );

        $this->filters['status'] = ['cond' => true, 'values' => $names];
    }

    /**
     * All closed statuses.
     */
    protected function allClosed()
    {
        $statuses = queryBuilder()->select('id', 'name')->from(PREFIX . 'statuses')
            ->where('status <= 0')
            ->execute();

        $ids = [];
        $names = [];
        foreach ($statuses->fetchAll() as $status) {
            $ids[] = $status['id'];
            $names[] = $status['name'];
        }

        $this->builder->andWhere(
            $this->expr->in('t.status_id', $ids)
        );

        $this->filters['status'] = ['cond' => true, 'values' => $names];
    }

    /**
     * Get filter information.
     */
    protected function extract($filter)
    {
        if (isset($this->query[$filter])) {
            $info = [
                'cond' => $this->query[$filter][0] == '!' ? false : true
            ];
            $info['values'] = $info['cond'] ? $this->query[$filter] : substr($this->query[$filter], 1);

            return $info;
        }

        return false;
    }

    /**
     * Quote string.
     */
    protected function quote($string)
    {
        return $GLOBALS['db']->quote($string);
    }

    /**
     * Filter field with `LIKE`.
     *
     * @param string $filterName
     * @param string $field
     */
    protected function likeFilter($filterName, $field)
    {
        $info = $this->extract($filterName);

        $info['values'] = explode(',', str_replace('*', '%', $info['values']));

        foreach ($info['values'] as $value) {
            if ($info['cond']) {
                $expr = $this->expr->like($field, "'%{$value}%'");
            } else {
                $expr = $this->expr->notLike($field, "'%{$value}%'");
            }
        }

        $this->builder->andWhere($expr);

        $this->filters[$filterName] = $info;
    }
}
