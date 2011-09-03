SET NAMES 'utf8';
SELECT CONCAT('$', REPLACE(LOWER(name), ' ', '_'), ' = _("', name, '");') FROM uo_country ORDER BY name;
