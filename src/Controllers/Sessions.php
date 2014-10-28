<?php
/*!
 * Traq
 * Copyright (C) 2009-2014 Jack Polgar
 * Copyright (C) 2012-2014 Traq.io
 * https://github.com/nirix
 * http://traq.io
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

use Radium\Http\Request;
use Traq\Models\User;

/**
 * User sessions controller.
 *
 * @author Jack P.
 * @package Traq\Controllers
 * @since 4.0
 */
class Sessions extends AppController
{
    public function __construct()
    {
        parent::__construct();

        $this->before(['new', 'create'], function(){
            if (LOGGEDIN) {
                $this->redirectTo('/');
            }
        });
    }

    /**
     * Login form
     */
    public function newAction() {}

    /**
     * Create session
     */
    public function createAction()
    {
        $this->setView('Sessions/new');

        if ($user = User::find('username', Request::$post['username'])
        and $user->authenticate(Request::$post['password'])) {
            if ($user->isActivated()) {
                setcookie('_traq', $user->login_hash, time() + (2 * 4 * 7 * 24 * 60 * 60 * 60), '/');
                $this->redirectTo(isset(Request::$post['redirect']) ? Request::$post['redirect'] : '/');
            } else {
                $this->set('activationRequired', true);
            }
        }
    }

    /**
     * Destroy session
     */
    public function destroyAction()
    {
        setcookie('_traq', sha1(time()), time() + 5, '/');
        $this->redirectTo('/');
    }
}