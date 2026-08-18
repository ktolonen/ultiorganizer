<?php

require_once __DIR__ . '/../include_only.guard.php';
denyDirectCustomizationAccess(__FILE__);

// Default pool color palettes.
// Consumed via PoolColors() in lib/pool.functions.php for:
// - assigning colors when creating/copying pools
// - plugin updater at ?view=plugins/update_pool_colors
// To customize, create/override cust/<CUSTOMIZATIONS>/pool_colors.php
// and return an array of 6-digit hex colors (without '#').
//
// Palette selection is deliberately local and hardcoded. Change only the key
// in the return statement at the bottom when testing another shipped palette.
$poolColorPalettes = [
    // Ultiorganizer palette designed for the 30% tint over a white table row.
    // Entries are ordered so pools created together receive dissimilar colors.
    "ultiorganizer" => [
        "00E5D2", "FF9226", "FFD400", "446600", "00747F", "B2FF8C", "9900CC", "7E7EE5",
        "FF72C4", "00B276", "FFAA00", "E55322", "FF003F", "00E55F", "FFFF72", "B20094",
        "FF8C9F", "59FFD5", "39C8E5", "FFFF00", "E52600", "007F54", "48B23E", "6A00FF",
        "E53964", "0CFFAE", "72FFFF", "FF00BF", "B959FF", "FF5400", "39E547", "CFFF3F",
        "B20059", "E53939", "E8A5FF", "70CC7F", "00B2B2", "599900", "FF00FF", "0CFF35",
        "0059B2", "FF2692", "CC0010", "5F9954", "5BCCAF", "72FFA1", "265CFF", "9E70CC",
    ],

    // Complete Colorcet glasbey_dark palette, whose colors are constrained for
    // contrast against light backgrounds.
    "glasbey-light-background" => [
        "D70000", "8C3CFF", "028800", "00ACC7", "E7A500", "FF7FD1", "6C004F", "583B00",
        "005759", "15E18D", "0000DD", "A2766A", "BCB7FF", "C004B9", "645473", "790000",
        "0774D8", "739B7D", "FF7852", "004B00", "8F7B01", "F3007B", "8FBA00", "A67BB8",
        "5A02A3", "E3AFAF", "A03A52", "A2C8C8", "9E4B00", "546745", "BBC389", "5F7B88",
        "60383C", "8388FF", "390000", "E353FF", "305382", "7FCAFF", "C5668F", "00816A",
        "929EB7", "CC7407", "7F2B8E", "00BEA4", "2DB152", "4E33FF", "00E500", "FF00CE",
        "C85848", "E59CFF", "1DA1FF", "6E70AB", "C89A69", "78573B", "04DAE6", "C1A3C4",
        "FF6A8A", "BB00FE", "925380", "9F0274", "94A150", "374425", "AF6DFF", "596D00",
        "FF3147", "838057", "006D2E", "8956AF", "5A4AA3", "773516", "86C39A", "5F1123",
        "D58581", "A42918", "0088B1", "CB0044", "FFA056", "EB4E00", "6C9700", "538649",
        "755A00", "C8C440", "92D370", "4B9894", "4D230D", "61345C", "8400CF", "8B0031",
        "9F6E32", "AC8499", "C63189", "025438", "086B84", "87A8EC", "6466EF", "C45DBA",
        "019F70", "815159", "836F8C", "B3C0DA", "B99129", "FF97B2", "A793E1", "698DBE",
        "4C5001", "4802CC", "61006E", "456A66", "9D5743", "7BACB5", "CD84BD", "0054C1",
        "7B2F4F", "FB7C00", "34C000", "FF9C88", "E1B769", "536177", "5C3A7C", "EDA5DA",
        "F053A3", "5D7E69", "C47750", "D14868", "6E00EB", "1F3400", "C14104", "6DD5C2",
        "46709F", "A201C4", "0A8289", "AFA601", "A65C6B", "FE77FF", "8B85AE", "C77FE9",
        "9AAB85", "876CD9", "01BAF7", "AF5ED2", "59512B", "B6005F", "7CB66A", "4985FF",
        "00C282", "D295AB", "A34BA8", "E306E3", "16A300", "392E00", "843033", "5E95AA",
        "5A1000", "7B4600", "6F6F31", "335826", "4D60B6", "A29564", "624028", "45D458",
        "70AAD0", "2E6B4E", "73AF9E", "FD1500", "D8B492", "7A893B", "7DC6D9", "DC9137",
        "EC615E", "EC5FD4", "E57BA7", "A66C98", "009744", "BA5F22", "BCAD53", "88D830",
        "873573", "AEA8D2", "E38C63", "D1B1EC", "37429F", "3ABEC2", "669D4D", "9E0399",
        "4E4E7A", "7B4C86", "C33531", "8D6677", "AA002D", "7F0175", "01824D", "734A67",
        "727791", "6E0099", "A0BA52", "E16E31", "C56A71", "6D5B96", "A33C74", "326200",
        "880050", "335869", "BA8D7C", "1959FF", "919202", "2C8BD5", "1726FF", "21D3FF",
        "A490AF", "8B6D4F", "5E213E", "DC03B3", "6F57CA", "652821", "AD7700", "A3BFF7",
        "B58446", "9738DC", "B25194", "7242A3", "878FD1", "8A70B1", "6BAF36", "5A7AC9",
        "C79FFF", "56841A", "00D6A7", "824739", "11431D", "5AAB75", "915B01", "F64570",
        "FF9703", "E14231", "BA92CF", "34584D", "F8807D", "913400", "B3CD00", "2E9FD3",
        "798B9F", "51817D", "C136D7", "EC0553", "B9AC7E", "487032", "849565", "D99D89",
        "0064A3", "4C9078", "8F6198", "FF5338", "A7423B", "006E70", "98843E", "DCB0C8",
    ],

    // Complete Colorcet glasbey_light palette, whose colors are constrained for
    // contrast against dark backgrounds.
    "glasbey-dark-background" => [
        "D70000", "028800", "B600FF", "06ACC6", "98FF00", "FFA530", "FF8FC8", "79525F",
        "00FECF", "B0A5FF", "94AD84", "9A6900", "376A62", "D3008C", "FEF590", "C86F66",
        "9EE3FF", "00C946", "A977AD", "B8BB02", "F4C0B1", "FF28FD", "F3CEFF", "009F7D",
        "FF6200", "56652B", "963F1F", "91318F", "FF3465", "A0E492", "8D9BB2", "829126",
        "AE093F", "78C7BB", "BC9258", "E58FFF", "72B9FF", "C6A5C1", "FF9171", "D3C37D",
        "BDEEDB", "6B8568", "926E56", "F9FF00", "BAC2E0", "AD577D", "FFCE03", "FF4AB1",
        "C25703", "5D8C90", "C244BD", "007540", "BA6FFE", "00D494", "00FF76", "49A251",
        "CC9891", "00EBEE", "DB7E01", "F8758A", "B99600", "C94248", "00D0FA", "765827",
        "85D401", "ECFFD4", "A77B88", "DC72C9", "CBE357", "8BBF5E", "A1216B", "865B89",
        "8ABBD0", "FFBAD7", "B7CFAB", "97414E", "68AB00", "FEE1B2", "FF3729", "807A3E",
        "D7E8FF", "A795C6", "7EA59B", "D183A4", "54823B", "E6A973", "9CFFFF", "DA5581",
        "05B4AA", "FFABF6", "D1AFEF", "DA025E", "AC1B13", "60B385", "D542FD", "ADAB59",
        "FB9DA7", "B3723C", "F26A53", "AED2D5", "9BFFC4", "DBB333", "EC02C3", "9900C5",
        "D0FF9E", "A65A4A", "3C6D01", "00857A", "959267", "8ADCB3", "6D7400", "AA5ECA",
        "07F000", "814F3E", "D98152", "FFC863", "B8009F", "99ACDE", "914F00", "8C4570",
        "4F6E52", "FF8834", "C78FCE", "D5E29E", "B2826D", "9DFB75", "57DE77", "FA0087",
        "A2CDFF", "14CBD2", "118F55", "D254A5", "00DFC3", "A3842F", "77975B", "BBAB80",
        "70A3B0", "D6FBFF", "E8023A", "D84722", "FF83ED", "B73863", "B7CE72", "98626B",
        "8A7491", "00A317", "00F5A1", "C091F2", "8AE4D8", "A44E95", "6E5E00", "8CC68E",
        "95AA2B", "C773DD", "B43B01", "D79A37", "DFADB7", "009BA0", "5A9000", "97BCA8",
        "AD8DA8", "DAD5FF", "557D72", "00BB69", "FFC48E", "B900D4", "E0D05B", "639A7B",
        "C0EEBC", "C2BEFE", "80D3DE", "E2857E", "FAEB4E", "C06D83", "CBFF50", "F072AA",
        "ED68FF", "9947AE", "6D6943", "E35761", "DD662D", "9DDB5D", "E29DD0", "B97600",
        "C6002D", "DFBDDA", "5AB6DF", "FF5ADA", "38C2A1", "9E6A8C", "ADAAC8", "966330",
        "B65662", "2C7F60", "B2E400", "EEA591", "95FEE2", "FF558E", "BE6FA1", "AA3C37",
        "D9CF00", "AB80CE", "A08052", "E100E8", "C35C3E", "B53A85", "8C7800", "DBBC96",
        "529E93", "B0BD83", "92B6B7", "A75424", "FFD5EF", "79AE6B", "5EB54C", "80FB9B",
        "48FFEF", "989648", "9488A7", "32D500", "6EEA56", "B7D4EB", "705570", "F2DB8B",
        "ABD5C2", "7FCDF2", "8ABB00", "65B7BB", "FFB600", "C38286", "CBAB5F", "657848",
        "59E3FF", "DF4ECD", "EAFF79", "BD66B9", "C495A6", "64C674", "D19570", "70CF4F",
        "AB6E66", "9D61A5", "00B800", "E399B4", "BD006C", "B3E9F0", "CEBFE4", "77A343",
        "856278", "578F5C", "9EB0C5", "E830A0", "257C2A", "826823", "C0BC4E", "DDD3A5",
    ],

    // The compact Okabe-Ito palette for color-vision-deficiency-aware use.
    // With only eight entries, large divisions will necessarily reuse colors.
    "okabe-ito" => [
        "E69F00", "56B4E9", "009E73", "F0E442", "0072B2", "D55E00", "CC79A7", "000000",
    ],
];

// Glasbey palette data is adapted from HoloViz Colorcet revision
// 9f23c9659e81a3bd9b23c7bf874d5c9323f07c16, licensed CC BY 4.0:
// https://github.com/holoviz/colorcet/tree/9f23c9659e81a3bd9b23c7bf874d5c9323f07c16/assets/Glasbey
return $poolColorPalettes["ultiorganizer"];
