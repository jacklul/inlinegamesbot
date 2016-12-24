#!/bin/bash
#
# Inline Games - Telegram Bot (@inlinegamesbot)
#
# Copyright (c) 2016 Jack'lul <https://jacklul.com>
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or#
#  (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#

echo "Status: 200 OK\r\n"
POST_DATA=$(cat)

echo "Content-Type: application/json"
echo ""

# Handle the request
if [ -f "./prehook.php" ]
then
    /usr/local/bin/php prehook.php $POST_DATA || /usr/local/bin/php webhook.php $POST_DATA > /dev/null 2>&1 &
else
    /usr/local/bin/php webhook.php $POST_DATA > /dev/null 2>&1 &
fi

exit;
