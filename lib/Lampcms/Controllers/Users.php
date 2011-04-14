<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms\Controllers;

use Lampcms\Responder;

use Lampcms\WebPage;
use Lampcms\Paginator;
use Lampcms\Template\Urhere;
use Lampcms\Request;


/**
 * Controller for rendering
 * the "Members" page
 *
 * If request is by Ajax, it returns only the content
 * of the main area, paginated, sorted and with pagination
 * links if necessary
 *
 * @author Dmitri Snytkine
 *
 */
class Users extends WebPage
{
	/**
	 * Users to show per page
	 *
	 * @var int
	 */
	protected $perPage = 15;

	protected $pagerPath = '/users/rep';


	/**
	 * Will show this page
	 * in 1-column layout, no right side nav
	 *
	 * @var int
	 */
	protected $layoutID = 1;


	/**
	 * Indicates the current tab
	 *
	 * @var string
	 */
	protected $qtab = 'users';


	/**
	 * Value of the sort $_GET param
	 *
	 * @var string
	 */
	protected $sort = 'rep';


	/**
	 * Condition for MongoCursor sort
	 *
	 * Defaults to sort by reputation in
	 * Descending order
	 *
	 * @var array
	 */
	protected $sortOrder = array('i_rep' => -1);


	/**
	 * Mongo Cursor
	 *
	 * @var object of type MongoCursor
	 */
	protected $oCursor;


	/**
	 * Total number of users
	 *
	 * @var int
	 */
	protected $usersCount;

	/**
	 * Html block with users
	 *
	 * @var string html
	 */
	protected $usersHtml;


	protected function main(){

		$this->init()
		->getCursor()
		->paginate()
		->renderUsersHtml();

		/**
		 * In case of Ajax request, just return
		 * the content of the usersHtml
		 * and don't proceed any further
		 */
		if(Request::isAjax()){
			Responder::sendJSON(array('paginated' => $this->usersHtml));
		}

		$this->setTitle()
		->makeSortTabs()
		->makeTopTabs()
		->setUsers();
	}


	/**
	 * Set value for title meta and title on page
	 *
	 * @todo translate string
	 *
	 * @return object $this
	 */
	protected function setTitle(){
		$title = $this->oRegistry->Ini->SITE_TITLE.' Members';
		$this->aPageVars['title'] = $title;
		$this->aPageVars['qheader'] = '<h1>'.$title.'</h1>';

		return $this;
	}


	/**
	 * Initialize some instance variables
	 * based on "sort" request param
	 *
	 * @throws \InvalidArgumentException if sort param is invalid
	 *
	 * @return object $this
	 */
	protected function init(){

		$this->perPage = $this->oRegistry->Ini->PER_PAGE_USERS;


		$this->sort = $this->oRegistry->Request->get('sort', 's', 'rep');
		if(!in_array($this->sort, array('rep', 'new', 'old', 'active'))){
			throw new \InvalidArgumentException('Invalid value of "sort" param. Valid values are "new", "old" or "rep" or "active". Was: '.$this->sort);
		}

		switch($this->sort){
			case 'active':
				$this->sortOrder = array('i_lm_ts' => -1);
				$this->pagerPath = '/users/active';
				break;

			case 'rep':
				$this->sortOrder = array('i_rep' => -1);
				$this->pagerPath = '/users/rep';
				break;

			case 'new':
				$this->sortOrder = array('_id' => -1);
				$this->pagerPath = '/users/new';
				break;


			case 'old':
				$this->sortOrder = array('_id' => 1);
				$this->pagerPath = '/users/old';
				break;
		}

		return $this;
	}


	/**
	 * Sets top tabs for the page
	 * making the "Members" the current active tab
	 *
	 * @return object $this
	 */
	protected function makeTopTabs(){
		d('cp');
		$tabs = Urhere::factory($this->oRegistry)->get('tplToptabs', $this->qtab);
		$this->aPageVars['topTabs'] = $tabs;

		return $this;
	}



	/**
	 * Paginate the results of cursor
	 *
	 * @return object $this
	 */
	protected function paginate(){

		d('paginating');
		$oPaginator = Paginator::factory($this->oRegistry);
		$oPaginator->paginate($this->oCursor, $this->perPage,
		array('path' => $this->pagerPath));

		$this->pagerLinks = $oPaginator->getLinks();

		d('$this->pagerLinks: '.$this->pagerLinks);

		return $this;
	}


	/**
	 * Sets the value of "sort by" tabs
	 *
	 * Will not add any tabs if there are fewer than 4 users on the site
	 * because there are just 4 "sort by" tabs
	 * and there is no need to sort the results
	 * of just 4 items
	 *
	 * @return object $this
	 */
	protected function makeSortTabs(){

		$tabs = '';

		/**
		 * Does not make sense to show
		 * any type of 'sort by' when
		 * Total number of users is
		 * fewer than number of "sort by" tabs
		 */
		if($this->usersCount > 4){

			$tabs = Urhere::factory($this->oRegistry)->get('tplUsertypes', $this->sort);
		}

		$aVars = array(
		$this->usersCount,
		(1 === $this->usersCount) ? 'User' : 'Users',
		$tabs
		);

		$this->aPageVars['body'] .= \tplUsersheader::parse($aVars, false);

		return $this;

	}


	/**
	 * Runs the find() in the USERS collection
	 * and sets the $this->oCursor instance variable
	 *
	 * @return object $this
	 */
	protected function getCursor(){
		$where = array('role' => array('$ne' => 'deleted'));
		/**
		 * Moderators can see deleted viewers
		 */
		if($this->oRegistry->Viewer->isModerator()){
			$where = array();
		}

		$this->oCursor = $this->oRegistry->Mongo->USERS->find($where,
		array(
			'_id', 
			'i_rep', 
			'username', 
			'fn', 
			'mn', 
			'ln',  
			'email', 
			'avatar', 
			'avatar_external', 
			'i_reg_ts', 
			'i_lm_ts', 
			'role'
			)
			);

			$this->oCursor->sort($this->sortOrder);
			$this->usersCount = $this->oCursor->count();

			return $this;
	}


	/**
	 * Renders the content of the members block
	 * and sets the $this->usersHtml instance variable
	 * but does not yet add them to page
	 * The Ajax based request will just
	 * grab the result of this variable
	 *
	 * @return object $this
	 */
	protected function renderUsersHtml(){
		$func = null;
		$aGravatar = $this->oRegistry->Ini->getSection('GRAVATAR');

		if(count($aGravatar) > 0){
			$func = function(&$a) use ($aGravatar){
				$a['gravatar'] = $aGravatar;
			};
		}

		$this->usersHtml = '<div class="users_wrap">'.\tplU3::loop($this->oCursor, true, $func).$this->pagerLinks.'</div>';

		return $this;
	}


	/**
	 * Adds the content of users block
	 * to page body
	 *
	 * @return object $this
	 */
	protected function setUsers(){
		$this->aPageVars['body'] .= '<div id="all_users" class="sortable paginated" lampcms:total="'.$this->usersCount.'" lampcms:perpage="'.$this->perPage.'">'.$this->usersHtml.'</div>';

		return $this;
	}

}