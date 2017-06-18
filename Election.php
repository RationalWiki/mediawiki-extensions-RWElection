<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

#configuration

$wgEligibleCount = 75;
$wgEligibleAge = 3*30*24*60*60; // 3 moths

$wgAutopromote['eligible'] = array( '&',
        array( APCOND_EDITCOUNT, &$wgEligibleCount ),
        array( APCOND_AGE, &$wgEligibleAge ),
   );

$wgGroupPermissions['eligible']['eligible'] = true;
$wgImplicitGroups[] = 'eligible';

$wgElectionIP = dirname( __FILE__ );
$wgExtensionMessagesFiles['Election'] = "$wgElectionIP/Election.i18n.php";
$wgAutoloadClasses['SpecialElection'] =  "$wgElectionIP//Election.body.php";

$wgSpecialPages['Election'] = 'SpecialElection';

$wgHooks['ParserFirstCallInit'][] = 'wfElectionInit';

function wfElectionInit( &$parser ) {
  wfLoadExtensionMessages('Election');
  return true;
}
