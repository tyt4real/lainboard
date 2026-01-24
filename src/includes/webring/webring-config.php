<?php

// Declaring stuff, ignore this.
$webring = array();
$webring['following'] = array();
$webring['known'] = array();
$webring['blacklist'] = array();
$webring['boards'] = array();
$webring['logo'] = array();
$webring['flags'] = array();

/*------------------------USER CONFIG STARTS HERE------------------------*/

// Site display name
$webring['name'] = 'Lainboard';

// Site base URL
$webring['url'] = 'https://boards.lain.rocks';

// Location of the webring json
// This is so that this node in the webring has a way to know its location and thus not follow itself
// Defaults to /webring.json
$webring['endpoint'] = $webring['url'] . '/webring.json';

// Site logo(s)
// Can have more than one
// Defaults to default vichan location
$webring['logo'][] = $webring['url'] . '/static/favicon.ico';
// $webring['logo'][] = 'https://image.board/anotherlogo.png';

// Other sites in the ring to be followed (add as few/many as desired)
$webring['following'][] = 'https://arisuchan.xyz/webring.json';
$webring['following'][] = 'https://smuglo.li/webring.json';
$webring['following'][] = 'https://tvch.moe/webring.json';
$webring['following'][] = 'https://trashchan.xyz/webring.json';
$webring['following'][] = 'https://zzzchan.xyz/webring.json';

// Domains to be blacklisted and not followed or parsed (add as few/many as desired)
// Note that this should just be the domain itself, no https:// or anything
$webring['blacklist'][] = '4chan.org';

// Uncomment if this site requires OpenNIC to browse
//$webring['flags'][] = 'opennic';

// Uncomment if this site requires TOR to browse
//$webring['flags'][] = 'tor';

