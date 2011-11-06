<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class Chan extends Public_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->library('template');
		$this->load->helper('number');
		$boards = new Board();
		$boards->order_by('shortname', 'ASC')->get();
		$this->template->set('boards', $boards);
		$this->template->set_partial('reply', 'reply');
		$this->template->set_layout('chan');
	}


	/*
	 * Show the boards
	 */
	public function index()
	{
		$this->template->title(get_setting('fs_gen_title'));
		$this->template->set('disable_headers', TRUE);
		$this->template->build('index');
	}
	
	public function page($page = 1)
	{
		$board = $this->db->protect_identifiers('board_' . $this->fu_board, TRUE);
		
		$query = $this->db->query('
			SELECT ' . $board . '.* FROM
			(
				SELECT a.*
				FROM ' . $board . ' AS a
				WHERE a.parent = 0

				UNION ALL

				SELECT b.*
				FROM ' . $board . ' AS b
				WHERE b.parent > 0
				GROUP BY b.parent

				ORDER BY num DESC
				LIMIT 0, 10
			)
			AS threads
			JOIN ' . $board . '
			ON threads.num = ' . $board . '.num
				OR threads.num = ' . $board . '.parent
			ORDER BY CASE ' . $board . '.parent 
				WHEN 0 THEN ' . $board . '.num 
				ELSE ' . $board . '.parent 
				END
				desc,num,subnum asc
			;
		');
		echo $this->db->last_query();
		foreach($query->result() as $row)
		{
			echo '<pre>';
			print_r($row);
			echo '</pre>';
		}
				
		
		foreach($posts->all as $key => $post)
		{
			$posts->all[$key]->post = new Post();
			$posts->all[$key]->post->where(array('parent' => $post->num, 'subnum' => 0))->order_by('num', 'DESC')->limit(5)->get();
		}
		
		$this->template->title('/'. get_selected_board()->shortname .'/ - '. get_selected_board()->name);
		$this->template->set('posts', $posts);
		$this->template->build('board');
	}
	
	public function ghost($page = 1)
	{
		/*
		// obtain list of parent ids with posts, nasty hack
		$values = array();
		$ghosts = new Post();
		$ghosts->where('subnum >', 0)->order_by('num', 'DESC')->group_by('parent')->get();
		foreach($ghosts->all as $key => $ghost)
		{
			$values[] = $ghost->parent;
		}
		
		if(empty($values))
		{
			$values[] = 0;
		}
		
		// obtain list of threads with list of ids
		$posts = new Post();
		$posts->where_in('num', $values)->get_paged($page, 25);
		foreach($posts->all as $key => $post)
		{
			$posts->all[$key]->post = new Post();
			$posts->all[$key]->post->where('parent', $post->num)->order_by('num', 'DESC')->limit(5)->get();
		}
		 */
		
		$values = array();
		$this->db->select('parent')->from('board_' . get_selected_board()->shortname . '_local')->order_by('num', 'DESC');
		$ghosts = $this->db->get();
		foreach($ghosts->result() as $key => $ghost)
		{
			$values[] = $ghost->parent;
		}
		
		if(empty($values))
		{
			$values[] = '';
		}
		
		$posts = new Post();
		$posts->where_in('num', $values)->get_paged($page, 25);
		foreach($posts->all as $key => $post)
		{
			$posts->all[$key]->post = new Post();
			$posts->all[$key]->post->where('parent', $post->num)->order_by('num', 'DESC')->limit(5)->get();
		}
		
		$this->template->title('/'. get_selected_board()->shortname .'/ - '. get_selected_board()->name);
		$this->template->set('posts', $posts);
		$this->template->build('board');
	}
	
	public function thread($num = 0)
	{
		if(!is_numeric($num) || !$num > 0)
		{
			show_404();
		}
		
		$thread = new Post();
		$thread->where('num', $num)->limit(1)->get();
		if($thread->result_count() == 0)
		{
			show_404();
		}
		
		$post_data = '';
		if ($this->input->post())
		{
			// LETS HANDLE THE GHOST POSTS HERE
			$post_data = $this->input->post();
		}
		
		$thread->all[0]->post = new Post();
		$thread->all[0]->post->where('parent', $num)->order_by('num', 'DESC')->get();
		$this->template->title('/'. get_selected_board()->shortname .'/ - '. get_selected_board()->name . ' - Thread #' . $num);
		$this->template->set('posts', $thread);
		
		$this->template->set_partial('reply', 'reply', array('thread_id' => $num, 'post_data' => $post_data));
		$this->template->build('board');
	}
	
	public function post($num = 0)
	{
		if(!is_numeric($num) || !$num > 0)
		{
			show_404();
		}
		
		$post = new Post();
		$post->where('num', $num)->get();
		if($post->result_count() == 0)
		{
			show_404();
		}
		
		$url = site_url($this->fu_board . '/thread/' . $post->parent) . '#' . $post->num;
		
		$this->template->title(_('Redirecting...'));
		$this->template->set('url', $url);
		$this->template->build('redirect');
	}
	
	public function image($hash, $limit = 25)
	{
		if($hash == '' || !is_numeric($limit))
		{
			show_404();
		}
		
		$posts = new Post();
		$posts->where('media_hash', urldecode($hash) . '==')->limit($limit)->order_by('num', 'DESC')->get();
		$this->template->title('/'. get_selected_board()->shortname .'/ - '. get_selected_board()->name . ' - Image: ' . urldecode($hash));
		$this->template->set('posts', $posts);
		$this->template->build('board');
	}
	
	public function search($query, $username = NULL, $tripcode = NULL, $deleted = 0, $internal = 0, $order = 'desc')
	{
		$posts = new Post();
		$this->template->title('/'. get_selected_board()->shortname .'/ - '. get_selected_board()->name . ' - Search: ' . $query);
		//$this->template->set('posts', $posts);
		$this->template->build('board');
	}
	
	public function _remap($method, $params = array())
	{
		$this->fu_board = $method;
		if(isset($params[0]))
		{
			$board = new Board();
			if(!$board->check_shortname($this->fu_board))
			{
				show_404();
			}
			$this->template->set('board', $board);
			$method = $params[0];
			array_shift($params);
		}
		/**
		 * ADD CHECK IF BOARD EXISTS
		 */
		
		if (method_exists($this->TC, $method))
		{
			return call_user_func_array(array($this->TC, $method), $params);
		}
		
		if (method_exists($this, $method))
		{
			return call_user_func_array(array($this, $method), $params);
		}
		show_404();
	}
	
}