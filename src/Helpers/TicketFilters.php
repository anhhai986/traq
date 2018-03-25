<?php
/*!
 * Traq
 * Copyright (C) 2009-2016 Jack Polgar
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

namespace Traq\Helpers;

use Avalon\Hook;
use Avalon\Language;
use Traq\Models\Project;
use Traq\Models\CustomField;
use Traq\Models\Type;
use Traq\Models\Status;
use Traq\Models\Component;
use Traq\Models\Priority;
use Traq\Models\Severity;

/**
 * Ticket filters helper.
 *
 * @author Jack P.
 */
class TicketFilters
{
    /**
     * Returns an array of all ticket filters, including custom fields
     * for the project.
     *
     * @param Project $project
     *
     * @return array
     */
    public static function filtersFor(Project $project)
    {
        static $filters;

        if ($filters !== null && count($filters)) {
            return $filters;
        }

        $filters = static::filters();

        foreach (static::customFieldFiltersFor($project) as $field => $name) {
            $filters[$field] = $name;
        }

        return $filters;
    }

    /**
     * Returns an array of custom field ticket filters for the specified project.
     *
     * @param Project $project
     *
     * @return array
     */
    public static function customFieldFiltersFor(Project $project)
    {
        static $filters = [];

        if (count($filters)) {
            return $filters;
        }

        foreach (CustomField::forProject($project->id) as $field) {
            $filters[$field->slug] = $field->name;
        }

        return $filters;
    }

    /**
     * Returns an array of available ticket filters.
     *
     * @return array
     *
     * @author Jack P.
     * @copyright Copyright (c) Jack P.
     * @package Traq
     */
    public static function filters()
    {
        $filters = [
            'summary'     => Language::translate('summary'),
            'description' => Language::translate('description'),
            'owner'       => Language::translate('owner'),
            'assigned_to' => Language::translate('assigned_to'),
            'component'   => Language::translate('component'),
            'milestone'   => Language::translate('milestone'),
            'version'     => Language::translate('version'),
            'status'      => Language::translate('status'),
            'type'        => Language::translate('type'),
            'priority'    => Language::translate('priority'),
            'severity'    => Language::translate('severity'),
            'search'      => Language::translate('search')
        ];

        // Run plugin hook
        Hook::run('function:ticket_filters', array(&$filters));

        return $filters;
    }

    /**
     * Returns an array of available ticket filters formatted for Form::select().
     *
     * @return array
     */
    public static function selectOptions(Project $project = null)
    {
        $options = [];

        // Add blank option
        $options[] = ['label' => '', 'value' => ''];

        // Ticket filters for a specific project
        if ($project !== null) {
            $filters = static::filtersFor($project);
        }
        // Default filters
        else {
            $filters = static::filters();
        }

        // Add filters
        foreach ($filters as $slug => $name) {
            $options[] = ['label' => $name, 'value' => $slug];
        }

        return $options;
    }

    /**
     * Returns options for the specific ticket filter.
     *
     * @param string $filter
     *
     * @return array
     */
    public static function selectOptionsFor($filter, Project $project)
    {
        switch ($filter) {
            // Milestone options
            case 'milestone':
                $options = $project->milestoneSelectOptions('slug');
                break;

            // Version options
            case 'version':
                $options = $project->milestoneSelectOptions('slug');
                break;

            // Type options
            case 'type':
                $options = Type::selectOptions('name');
                break;

            // Status options
            case 'status':
                $options = Status::selectOptions('name');
                break;

            // Component options
            case 'component':
                $options = Component::selectOptions($project->id, 'name');
                break;

            // Priority options
            case 'priority':
                $options = Priority::selectOptions('name');
                break;

            // Severity options
            case 'severity':
                $options = Severity::selectOptions('name');
                break;
        }

        return $options;
    }

    /**
     * Returns is/is not options for the `Form::select` helper.
     *
     * @return array
     */
    public static function isIsNotSelectOptions()
    {
        return [
            ['label' => Language::translate('is'), 'value' => '+'],
            ['label' => Language::translate('is_not'), 'value' => '-']
        ];
    }

    /**
     * Returns contains/doesn't contain options for the `Form::select` helper.
     *
     * @return array
     */
    public static function containsFilterSelectOptions()
    {
        return [
            ['label' => Language::translate('contains'), 'value' => '+'],
            ['label' => Language::translate('doesnt_contain'), 'value' => '-']
        ];
    }
}
