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

namespace Traq\Controllers\Admin;

use Avalon\Http\Request;
use Traq\Models\Group;
use Traq\Traits\Controllers\CRUD;

/**
 * Admin Groups controller.
 *
 * @package Traq\Controllers\Admin
 * @author Jack P.
 * @since 3.0.0
 */
class Groups extends AppController
{
    use CRUD;

    // Model class and views directory
    protected $model    = '\Traq\Models\Group';
    protected $viewsDir = 'admin/groups';

    // Singular and plural form
    protected $singular = 'group';
    protected $plural   = 'groups';

    // Redirect route names
    protected $afterCreateRedirect  = 'admin_groups';
    protected $afterSaveRedirect    = 'admin_groups';
    protected $afterDestroyRedirect = 'admin_groups';

    // Route names
    protected $newRoute = 'admin_new_group';
    protected $editRoute = 'admin_edit_group';

    public function __construct()
    {
        parent::__construct();
        $this->addCrumb($this->translate('groups'), $this->generateUrl('admin_groups'));
    }

    /**
     * @return array
     */
    protected function modelParams()
    {
        return [
            'name'     => Request::$post->get('name'),
            'is_admin' => Request::$post->get('is_admin', false)
        ];
    }
}
