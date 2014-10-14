<?php
class Vpdb {
	
	var $link_id = NULL;
	var $error_message = array();
	var $is_log = false;
	var $time;
	
	public function __construct($dbhost,$dbuser,$dbpwd,$dbname,$charset = 'utf8',$pconnect = 0)
	{
		$this->vp_mysql($dbhost,$dbuser,$dbpwd,$dbname,$charset,$pconnect);	
	}
	
	public function vp_mysql($dbhost,$dbuser,$dbpwd,$dbname,$charset = 'utf8',$pconnect = 0)
	{
		$this->connect($dbhost,$dbuser,$dbpwd,$dbname,$charset,$pconnect);
		$this->time = $this->microtime_float();
	}
	
	public function connect($dbhost,$dbuser,$dbpwd,$dbname,$charset = 'utf8',$pconnect = 0)
	{
		if($pconnect)
		{
			if(!($this->link_id=@mysql_pconnect($dbhost,$dbuser,$dbpwd)))
			{
				$this->show_error("Can't pconnect Mysql Server $dbhost!");
				
				return false;
			}	
		}
		else
		{
			if(!($this->link_id=@mysql_connect($dbhost,$dbuser,$dbpwd)))
			{
				$this->show_error("Can't connect Mysql Server $dbhost!");
				
				return false;
			}
		}
		if(mysql_select_db($dbname,$this->link_id) === false)
		{
			$this->show_error("Can't select Database ($dbname) !!");
			
			return false;	
		}
		mysql_query("set names $charset");
	}

	function insert_id()
	{
		return mysql_insert_id($this->link_id);
	}
	//exec the query 
	function query($sql)
	{
		if(!$this->link_id)
		{
			die("Mysql Link ID is Null , Die");
		}
		
		if($sql == '')
		{
			$this->show_error("SQL is null!");
			
			return false;
		}	
		
		if(!$query = mysql_query($sql,$this->link_id))
		{
			$this->error_message[]['message'] = "Query Error!!!!";
			$this->error_message[]['sql'] = $sql;
			$this->error_message[]['errno'] = mysql_errno($this->link_id);
			$this->error_message[]['error'] = mysql_error($this->link_id);
			
			$this->show_error();	
			return false; 	
		}
		
		if($this->is_log == true)
		{
			$logdir = "./mysql_log";
			$logfile = "./"."mysql_log/".date("Y-m-d").".log";
			$str = $sql . "\n";
			if(!file_exists($logdir))
			{
				if(!mkdir($logdir,0777))
				{
					$this->show_error("log dir is not exists and could not create it~!!!!");
					return false;		
				}		
			}
			
			if(PHP_VERSION >= '5.0')
			{
				file_put_contents($logfile,$str,FILE_APPEND);
			}
			else
			{
				$fp = @fopen($logfile,"ab+");
				if($fp)
				{
					fwrite($fp,$str);
					fclose($fp);	
				}
				else
				{
					$this->show_error("fopen error!!");	
				}	
			}
				
		}

		return $query;
	}

	function fetch_array($query,$result_type = MYSQL_ASSOC)
	{
		return mysql_fetch_array($query,$result_type);
	}
	
	function result($query,$num)
	{
		return @mysql_result($query,$num);
	}

	function num_rows($query)
	{
		return mysql_num_rows($query);
	}

	function num_fields($query)
	{
		return mysql_num_fields($query);
	}

	function fetch_row($query)
	{
		return mysql_fetch_row($query);	
	}
	
	function fetchRow($query)
	{
		return mysql_fetch_assoc($query);
	}

	function selectLimit($sql,$num,$start = 0)
	{
		if($start == 0)
		{
			$sql .= ' LIMIT '.$num;
		}
		else
		{
			$sql .= ' LIMIT '.$start.",".$num;
		}
		return $this->query($sql);
	}

	function free_result($result)
	{
		return mysql_free_result($result);
	}
	
	function getOne($sql,$limited = false)
	{
		if ($limited == true)
        {
            $sql = trim($sql . ' LIMIT 1');
        }

        $res = $this->query($sql);
        if ($res !== false)
        {
            $row = mysql_fetch_row($res);

            if ($row !== false)
            {
                return $row[0];
            }
            else
            {
                return '';
            }
        }
        else
        {
            return false;
        }
	}
	
	function getAll($sql)
	{
		$res = $this->query($sql);
		if($res !== false)
		{
			$arr = array();
			
			while($row = mysql_fetch_assoc($res))
			{
				$arr[] = $row;
			}
			return $arr;		
		}
		else
		{
			return false;
		}
	}
	
	function error()
	{
		return mysql_error($this->link_id);
	}

	function close()
	{
		return mysql_close($this->link_id);	
	}

	function show_error($msg = '')
	{
		if($msg)
		{
			echo "<b> Error Info </b> $msg <br/><br/>";
		}
		else
		{
			foreach($this->error_message as $key => $value)
			{
				foreach($value as $key2 => $value2)
				{
					echo $key2." : ".$value2."<br>";
				}
			}
		}		
	}
	
	function microtime_float()
	{
		list($msec, $sec) = explode(" ", microtime());
		return ((float)$msec + (float)$sec); 
	}
	
	public function __destruct() 
	{
		mysql_close($this->link_id);
		$use_time = ($this-> microtime_float())-($this->time);
	}
}
