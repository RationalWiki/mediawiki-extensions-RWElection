<?php

class ElectionForm {
	function ElectionForm( $par )
	{
	}

	function showForm( $err )
	{
		global $wgOut, $wgUser;
		$wgOut->setPagetitle( wfMessage('Election')->escaped() );
		if ( $err ) {
			$key = array_shift($err);
			$msg = wfMessage($key)->params($err);
			$wgOut->setSubtitle( wfMessage('formerror')->escaped() );
			$wgOut->addHTML( Xml::tags('p', array('class' => 'error'), $msg ) );
		}

		global $wgElectionCandidates, $wgElectionName;
 		$titleObject = SpecialPage::getTitleFor( 'Election' );
		$output =	Xml::openElement('form', array( 'method' => 'post', 'action' => $titleObject->getLocalURL("action=submit"), 'id' => 'election' ) );
		for ( $i=1; $i < count($wgElectionCandidates) + 1; $i++ ) {
			$output .= '<p>';
			$output .= Xml::label(wfMessage( 'election-rank' )->params( $i )->parse(),"candidate$i" ) . " ";
			$options = Xml::option(wfMessage('election-none')->text(),'-1',true);
			$j = 1;
			foreach ($wgElectionCandidates as $candidate) {
				$options .= Xml::option($candidate,$j);
				$j++;
			}
			$output .= Xml::openElement( 'select', array( 'name' => "candidate$i", 'id' => "candidate$i", 'tabindex' => "$i" ) )
                         . "\n"
                         . $options
                         . "\n"
                         . Xml::closeElement( 'select' );
			$output .= '</p>';
		}
		$output .= Html::hidden('wpEditToken', $wgUser->getEditToken() );
		$output .= Html::hidden('electionName', $wgElectionName );
		$output .= Html::hidden('electionCandidateCount', count($wgElectionCandidates) );
		$output .= Xml::submitButton( wfMessage( 'election-vote' )->text(),
                             array('name' => 'wpVote',
                                   'tabindex' => "$i",
                                   'accesskey' => 's') );
		$output .= Xml::closeElement( 'form' );
		$wgOut->addHTML($output);

	}

	function doVote()
	{
		global $wgUser, $wgElectionName, $wgRequest, $wgElectionCandidates, $wgElectionStoreDir;

		if ( !$wgElectionStoreDir ) {
			throw new MWException( 'Please set $wgElectionStoreDir to a writable directory' );
		}
		$electionName = $wgRequest->getVal('electionName',null);
		if ( is_null($electionName) || $electionName != $wgElectionName) {
			return array('election-mismatch');
		}
		$candidateCount = $wgRequest->getInt('electionCandidateCount');
		if ($candidateCount == 0 || $candidateCount != count($wgElectionCandidates)) {
			return array('election-mismatch');
		}
		$candidates = array();
		for ( $i=1; $i < $candidateCount + 1; $i++ )
		{
			$candidate = $wgRequest->getInt("candidate$i",-1);
			if ( $candidate > -1 ) {
				$candidates[$i-1] = $candidate;
			}
		}

		$unique = array_unique($candidates);

		if ( (count($candidates) - count($unique)) > 0 ) {
			return array('election-error');
		}

		//$wgUser->setOption('election' . $wgElectionName,'voted');
		$dbw = wfGetDB(DB_MASTER);
		$result = $dbw->insert('election_voted',array('ev_user' => $wgUser->getId(), 'ev_election' => $wgElectionName),__METHOD__);
		if ( $result ) {
			$dbw->commit();
			$us=$wgUser->getId();
			$f = fopen("{$wgElectionStoreDir}/{$wgElectionName}.blt", "a");
			fwrite($f,$us);
			fwrite($f," 1 ");
			for ( $i=0; $i < $candidateCount + 1; $i++) {
				if ( array_key_exists($i,$candidates) ) {
					fwrite($f, $candidates[$i]." ");
				}
			}
			fwrite($f, "0\r\n");
			fclose($f);
		}

		return array();
	}

	function doSubmit()
	{
		global $wgOut;
		$retval = $this->doVote();
		if (empty($retval)) {
			$titleObj = SpecialPage::getTitleFor('Election');
			$wgOut->redirect($titleObj->getFullURL('action=success' ));
			return;
		}
		$this->showForm($retval);
	}

	function showSuccess() {
		global $wgOut;
		$wgOut->setPagetitle( wfMessage( 'Election' )->escaped() );
		$text = wfMessage( 'election-thanks' )->escaped();
		$wgOut->addHTML( $text );
	}
}

class SpecialElection extends SpecialPage {
	function __construct() {
		parent::__construct('Election','eligible');
		$this->mIncludable = true;
	}

	function hasVoted() {
		global $wgUser,$wgElectionName;
		$dbr = wfGetDB(DB_MASTER);
		$res = $dbr->select('election_voted','ev_user, ev_election',array('ev_user' => $wgUser->getId(), 'ev_election' => $wgElectionName),__METHOD__);
		$voted = ($res->numRows() != 0);
		$res->free();
		return $voted;
	}

	function execute( $par ) {
		global $wgRequest, $wgOut, $wgUser, $wgElectionName;
		$this->setHeaders();

		if( wfReadOnly() ) {
			$wgOut->readOnlyPage();
			return;
		}

		if( is_null($wgElectionName) || $wgElectionName == '' ) {
			$wgOut->addHtml(wfMessage('election-closed')->escaped());
			return;
		}

		if( !$wgUser->isAllowed( 'eligible' ) || $wgUser->isBlocked() ) {
			$wgOut->addHtml(wfMessage('election-ineligible')->escaped());
			return;
		}

		$form = new ElectionForm( $par );

		$action = $wgRequest->getVal( 'action' );
		if ( 'success' == $action )
		{
			$form->showSuccess();
		} elseif ( $wgRequest->wasPosted() && 'submit' == $action &&
		           $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) ) {
			if ( $this->hasVoted() ) {
				$wgOut->addHtml(wfMessage('election-alreadyvoted')->escaped());
				return;
			} else {
				$form->doSubmit();
			}
		} else {
			if ( $this->hasVoted() ) {
				$wgOut->addHtml(wfMessage('election-alreadyvoted')->escaped());
				return;
			} else {
				$form->showForm( '' );
			}
		}

  }
}
