<?php
/**
 *Pagination Class
 *author : Vp 
*/
class Pagination{

	var $total_rows = 0;
	var $cur_page;
	var $total_pages;
	var $per_page = 10;
	var $offset;
	var $page_code;
	var $parameter = '';
	var $url;
	var $plus = 10;
	var $anchor_class = '';
	
	public function __construct($params = array())
	{
		if(count($params) > 0)
		{
			$this->initialize($params);
		}
	}
	
	function initialize($params = array())
	{
		if(count($params) > 0)
		{
			foreach($params as $key => $val)
			{
				if (isset($this->$key))
				{
					$this->$key = $val; //¸²¸Ç
				}
			}
			$this->page_code = !empty($params['page_code']) ? $params['page_code'] : 'page';
			$this->cur_page = !empty($_GET[$this->page_code]) ? $_GET[$this->page_code] : 1;
		}	
	}
	
	function create_links($page,$code)
	{
		if ($this->total_rows == 0 || $this->per_page == 0)
		{
			return '';
		}		
		
		$this->total_pages = ceil($this->total_rows / $this->per_page);
		if($this->total_pages == 1)
		{
			return '';
		}	
		return '<a href="'.$this->_get_url($page).'">'.$code.'</a>'."\n";
	}
	
	function _set_url()
	{
		$url = $_SERVER['REQUEST_URI'].(strpos($_SERVER['REQUEST_URI'],'?')?'':'?').$this->parameter;
		$parse = parse_url($url);
		if(isset($parse['query']))
		{
			parse_str($parse['query'],$params);
			unset($params[$this->page_code]);
			$url = $parse['path'].'?'.http_build_query($params); //ÖØ¹¹URL 
		}
		if(!empty($params))
        {
        	$url .= '&';
        }
		$this->url = $url;
	}
	function _get_url($page)
	{
		if($this->url === NULL)
    	{
    		$this->_set_url();	
    	}
    	return $this->url.$this->page_code.'='.$page;
	}
	function first_link($code = 'First')
	{
		if($this->cur_page >= 1)
 		{
 			return $this->create_links(1, $code);
 		}	
 		return '';
	}
	function last_link($code = 'Last')
	{
		if($this->cur_page <= $this->total_pages)
 		{
 			return $this->create_links($this->total_pages, $code);
 		}	
 		return '';
	}
	function prev_link($code = 'Prev')
	{
		if($this->cur_page != 1)
    	{
    		return $this->create_links($this->cur_page - 1, $code);
    	}
    	return '';
	}
	function next_link($code = 'Next')
	{
    	if($this->cur_page < $this->total_pages)
    	{
    		return $this->create_links($this->cur_page + 1, $code);
    	}
    	return '';
	}
	function page_list()
	{
		$list = '';
		$inum = floor($this->plus/2);
		for($i = $inum; $i >= 1; $i--)
		{
			$page = $this->cur_page-$i;
			if($page<1)
				continue;
			$list .= "<a href='{$this->url}{$this->page_code}={$page}'>{$page}</a>";
		}
		$list .= "<a class='current-page'>{$this->cur_page}</a>";
		for($i = 1; $i <= $inum; $i++)
		{
			$page = $this->cur_page+$i;
			if($page <= $this->total_pages)
				$list .= "<a href='{$this->url}{$this->page_code}={$page}'>{$page}</a>";
			else
				break;
		}

		return $list;
	}
	function go_link()
	{
		return '<input id="pagin-input" type="text" value='.$this->cur_page.'><a id="skip-link" href="javascript:;">确定</a>';
	}
	function display()
	{
		$output = '';
		$output .= $this->first_link('First');
		$output .= $this->prev_link('Prev');
		$output .= $this->page_list();
		$output .= $this->next_link('Next');
		$output .= $this->last_link('Last');
		$output .= $this->go_link();
		return $output;
	}
}
