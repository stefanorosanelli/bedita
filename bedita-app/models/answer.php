<?php
/*-----8<--------------------------------------------------------------------
 * 
 * BEdita - a semantic content management framework
 * 
 * Copyright 2008 ChannelWeb Srl, Chialab Srl
 * 
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the Affero GNU General Public License as published 
 * by the Free Software Foundation, either version 3 of the License, or 
 * (at your option) any later version.
 * BEdita is distributed WITHOUT ANY WARRANTY; without even the implied 
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the Affero GNU General Public License for more details.
 * You should have received a copy of the Affero GNU General Public License 
 * version 3 along with BEdita (see LICENSE.AGPL).
 * If not, see <http://gnu.org/licenses/agpl-3.0.html>.
 * 
 *------------------------------------------------------------------->8-----
 */

/**
 * Answer object. Related to QuestionnaireResult, Question, QuestionAnswer.
 *
 * @version			$Revision$
 * @modifiedby 		$LastChangedBy$
 * @lastmodified	$LastChangedDate$
 * 
 * $Id$
 */
class Answer extends BEAppModel
{

	var $belongsTo = array("QuestionnaireResult", "Question", "QuestionAnswer");
	
	public function countCorrectAnswers($questionnaire_result_id) {
		$corrects = $this->find("count", array(
				"conditions" => array("QuestionAnswer.correct" => 1, "questionnaire_result_id" => $questionnaire_result_id),
				"contain" => array("QuestionAnswer")
			)
		);
		return $corrects;
	}
	
	
}
?>
