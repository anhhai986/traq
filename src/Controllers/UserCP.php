<?php
/*!
 * Traq
 * Copyright (C) 2009-2018 Jack P.
 * Copyright (C) 2012-2018 Traq.io
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
use Avalon\Http\Response;
use Traq\Models\User;
use Traq\Models\Subscription;

/**
 * UserCP controller
 *
 * @package Traq\Controllers
 * @author Jack P.
 * @since 3.0.0
 */
class UserCP extends AppController
{
    public function __construct()
    {
        parent::__construct();

        $this->before('*', function () {
            $this->set('user', clone $this->currentUser);

            // Make sure the user is logged in
            if (!$this->currentUser) {
                $this->layout = "default.phtml";
                return $this->show403();
            }
        });
    }

    /**
     * @return \Avalon\Http\Response
     */
    public function indexAction()
    {
        return $this->render("usercp/index.phtml");
    }

    /**
     * @return \Avalon\Http\Response
     */
    public function saveAction()
    {
        $user = User::find($this->currentUser['id']);

        $data = array(
            'name'     => Request::$post->get('name', $user->name),
            'email'    => Request::$post->get('email', $user->email),
            'language' => Request::$post->get('language', $user->language)
        );

        $correctPassword = false;
        if (!$user->authenticate(Request::$post->get('current_password'))) {
            $user->addError('password', $this->translate('errors.incorrect_password'));
        } else {
            $correctPassword = true;
        }

        // Set the info
        $user->set($data);
        $user->validate();

        // Save the user
        if ($correctPassword && $user->save()) {
            return $this->respondTo(function ($format) use ($user) {
                if ($format == "html") {
                    return $this->redirectTo('usercp');
                } else {
                    return $this->jsonResponse($user);
                }
            });
        } else {
            return $this->render("usercp/index.phtml", ['user' => $user]);
        }
    }

    /**
     * Generate a (new) API key.
     *
     * @return Response
     */
    public function generateApiKeyAction()
    {
        $user = User::find($this->currentUser['id']);
        $user->generateApiKey();
        $user->save();

        return $this->respondTo(function ($format) use ($user) {
            if ($format == "html") {
                return $this->redirectTo('usercp');
            } elseif ($format == "js") {
                $resp = new Response($this->renderView('usercp/generate_api_key.js.php', [
                    '_layout' => false,
                    'user'    => $user
                ]));

                $resp->contentType = 'text/javascript';
                return $resp;
            }
        });
    }

    /**
     * Password page.
     *
     * @return \Avalon\Http\Response
     */
    public function passwordAction()
    {
        // Clone the logged in user object
        $user = User::find($this->currentUser->id);
        $this->set(compact('user'));

        return $this->render('usercp/password.phtml');
    }

    /**
     * Update password.
     *
     * @return \Avalon\Http\Response
     */
    public function savePasswordAction()
    {
        $user = User::find($this->currentUser['id']);
        $this->set(compact('user'));

        // Authenticate current password
        if (!$user->authenticate(Request::$post->get('current_password'))) {
            $user->addError('password', $this->translate('errors.incorrect_password'));
        } else {
            // Confirm passwords
            if (Request::$post->get('password') !== Request::$post->get('password_confirmation')) {
                $user->addError('password', $this->translate('errors.validations.confirm', ['field' => $this->translate('password')]));
            } else {
                $user->password = Request::$post->get('password');

                // Save and redirect
                if ($user->validate()) {
                    // Update password
                    $user->setPassword(Request::$post->get('password'));
                    $user->password_ver = 'crypt';
                    $user->save();

                    return $this->redirectTo('usercp_password');
                }
            }
        }

        // Incorrect password or new passwords don't match.
        return $this->render('usercp/password.phtml');
    }

    /**
     * Subscriptions page
     */
    public function action_subscriptions()
    {
        $subscriptions = Subscription::select()->where('user_id', $this->user->id)->exec()->fetch_all();
        View::set('subscriptions', $subscriptions);
    }
}
