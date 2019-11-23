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

namespace Traq\Database\Migrations;

use Avalon\Database\Migration;

class CreateUsers extends Migration
{
    public function up()
    {
        $this->createTable("users", function ($t) {
            $t->addColumn("username", "string");
            $t->addColumn("password", "string");
            $t->addColumn("password_ver", "string", ['default' => "crypt", 'notnull' => false]);
            $t->addColumn("name", "string");
            $t->addColumn("email", "string");
            $t->addColumn("group_id", "integer", ['default' => 2]);
            $t->addColumn("language", "string", ['default' => "enAU"]);
            $t->addColumn("options", "text", ['notnull' => false]);
            $t->addColumn("session_hash", "string");
            $t->addColumn("api_key", "string", ['notnull' => false]);
            // $t->addColumn("activation_code", "string", ['notnull' => false]);

            $this->timestamps($t);
        });
    }

    public function down()
    {
        $this->dropTable("users");
    }
}
