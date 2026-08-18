<?php

require_once __DIR__ . '/../include_only.guard.php';
denyDirectCustomizationAccess(__FILE__);

// Default pool color palette.
// Consumed via PoolColors() in lib/pool.functions.php for:
// - assigning colors when creating/copying pools
// - plugin updater at ?view=plugins/update_pool_colors
// To customize, create/override cust/<CUSTOMIZATIONS>/pool_colors.php
// and return an array of 6-digit hex colors (without '#').
//
// Entries are distinguishable from each other as drawn, the list is long enough
// to give every pool of a division its own color, and the order matters because
// pools created together take consecutive entries. See docs/customization.md
// before replacing or reordering the list.
return [
    "00E5D2",
    "FF9226",
    "FFD400",
    "446600",
    "00747F",
    "B2FF8C",
    "9900CC",
    "7E7EE5",
    "FF72C4",
    "00B276",
    "FFAA00",
    "E55322",
    "FF003F",
    "00E55F",
    "FFFF72",
    "B20094",
    "FF8C9F",
    "59FFD5",
    "39C8E5",
    "FFFF00",
    "E52600",
    "007F54",
    "48B23E",
    "6A00FF",
    "E53964",
    "0CFFAE",
    "72FFFF",
    "FF00BF",
    "B959FF",
    "FF5400",
    "39E547",
    "CFFF3F",
    "B20059",
    "E53939",
    "E8A5FF",
    "70CC7F",
    "00B2B2",
    "599900",
    "FF00FF",
    "0CFF35",
    "0059B2",
    "FF2692",
    "CC0010",
    "5F9954",
    "5BCCAF",
    "72FFA1",
    "265CFF",
    "9E70CC",];
