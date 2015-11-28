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

namespace Traq\Database\Migrations;

use Avalon\Database\Migration;

class CreateStatuses extends Migration
{
    public function up()
    {
        $this->createTable("statuses", function ($t) {
            $t->addColumn("name", "string");
            $t->addColumn("status", "integer", ['default' => 1]);
            $t->addColumn("show_on_changelog", "boolean", ['default' => true]);
        });
    }

    public function down()
    {
        $this->dropTable("statuses");
    }
}