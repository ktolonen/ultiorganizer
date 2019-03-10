#!/bin/sh

mysql --skip-column-names -r -p -u pelikone pelikone < get_countries.sql
