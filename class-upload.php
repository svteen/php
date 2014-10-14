<?php
class Upload{
	
	public $max_size = 0;
	public $allowed_type = "";
	public $file_type = "";
	public $file_name = "";
	public $file_temp = "";
	public $file_size = "";
	public $overwrite = FALSE;
	public $orig_name = "";
	public $error_msg = array();
	public $upload_path = "";
	public $encrypt_name = FALSE;	
	public $client_name = "";
		
	public function __construct($prama = array())
	{
		//print_r($prama);
		if(count($prama) > 0)
		{
			$this->init($prama);
		}
	}	
	public function init($config = array())
	{
		$default = array (
			'max_size' => 0,
			'allowed_type' => "",
			'file_type' => "",
			'file_name' => "",
			'file_temp' => "",
			'file_size' => "",
			'overwrite'	=> FALSE,
			'orig_name' => "",
			'error_msg' => array(),
			'upload_path' => "",
			'encrypt_name' => FALSE,
			'client_name' => ""
		);		
		
		foreach($default as $key => $val)
		{
			if(isset($config[$key]))
			{
				$method = 'set_'.$key;
				if(method_exists($this,$method))
				{
					$this->$method($config[$key]);	
				}
				else
				{
					$this->$key = $config[$key];
				}
			}
			else
			{
				$this->$key = $val;
			}
		}
		
	}
	
	public function do_upload($field = 'files')
	{
	
		if(! isset($_FILES[$field])) //没有该域，返回FALSE
		{
			return FALSE;
		}
		
		if(! $this->validate_upload_path())
		{
			return FALSE;
		}
	
		$this->file_temp = $_FILES[$field]['tmp_name'];
		$this->file_size = $_FILES[$field]['size'];
		//$this->_file_mime_type($_FILES[$field]);
		//$this->file_type = preg_replace("/^(.+?);.*$/", "\\1", $this->file_type);
		//$this->file_type = strtolower(trim(stripslashes($this->file_type), '"'));
		$this->file_name = $_FILES[$field]['name'];
		$this->file_ext	 = $this->get_extension($this->file_name);
		$this->client_name = $this->file_name;
		
		if(is_array($this->file_name))
		{	
			$arr = array();
			for($i = 0; $i < count($this->file_name); $i++) //循环上传文件.
			{
				$arr[$i] = trim($this->get_extension($this->file_name[$i]), ".");
				
				if(in_array($arr[$i], $this->allowed_type))
				{	
					if ( ! @copy($this->file_temp[$i], $this->upload_path.$this->file_name[$i]))
					{
						if ( ! @move_uploaded_file($this->file_temp[$i], $this->upload_path.$this->file_name[$i]))
						{
							$this->set_error('upload_destination_error');
							return FALSE;
						}
					}
				}
				else
				{
					$this->set_error("is_not_allowed_type");
					return FALSE;
				}
			}
			
			return TRUE; 
		}
		
		if(!is_uploaded_file($_FILES[$field]['tmp_name'])) //判断是否从HTTP POST 上传
		{	
		
			$error = ( ! isset($_FILES[$field]['error'])) ? 4 : $_FILES[$field]['error'];
			
			switch($error)
			{
				case 1:	// 超出服务器空间大小
					$this->set_error('upload_file_exceeds_limit');
					break;
				case 2: // 超出限制的大小
					$this->set_error('upload_file_exceeds_form_limit');
					break;
				case 3: // 文件部分上传
					$this->set_error('upload_file_partial');
					break;
				case 4: // 没有选择上传文件
					$this->set_error('upload_no_file_selected');
					break;
				case 6: // 没有临时文件夹
					$this->set_error('upload_no_temp_directory');
					break;
				case 7: // 上传文件夹不可写
					$this->set_error('upload_unable_to_write_file');
					break;
				case 8: //  - -、
					$this->set_error('upload_stopped_by_extension');
					break;
				default :   $this->set_error('upload_no_file_selected');
					break;
			}

			return FALSE;
		}
		
		if(! $this->is_allowed_type())
		{	
			$this->set_error("upload_invalid_filetype");
			return FALSE;
		}

		if ($this->file_size > 0)
		{
			$this->file_size = round($this->file_size/1024, 2);
		}

		if ( ! $this->is_allowed_size())
		{
			$this->set_error('upload_invalid_filesize');
			return FALSE;
		}

		$this->file_name = $this->clean_file_name($this->file_name);

		$this->orig_name = $this->file_name;

		if ($this->overwrite == FALSE)
		{
			$this->file_name = $this->set_filename($this->upload_path, $this->file_name);

			if ($this->file_name === FALSE)
			{
				return FALSE;
			}
		}

		if ( ! @copy($this->file_temp, $this->upload_path.$this->file_name))
		{
			if ( ! @move_uploaded_file($this->file_temp, $this->upload_path.$this->file_name))
			{
				$this->set_error('upload_destination_error');
				return FALSE;
			}
		}

		return TRUE;
	}
	
	public function set_error($msg)
	{
		$this->error_msg[] = $msg;
	}
	
	public function display_error($open = '<p>', $close = '</p>')
	{
		$str = '';
		foreach($this->error_msg as $val)
		{
			$str .= $open.$val.$close;
		}
		return $str;
	}
	
	public function set_allowed_type($types)
	{
		if ( ! is_array($types) && $types == '*')
		{
			$this->allowed_type = '*';
			return;
		}
		$this->allowed_type = explode('|', $types);
	}
	
	public function set_upload_path($path)
	{
		$this->upload_path = rtrim($path, '/').'/';
	}
	
	public function is_allowed_type()
	{
		if($this->allowed_type == '*')
		{
			return TRUE;
		}

		if(count($this->allowed_type) == 0 OR ! is_array($this->allowed_type))
		{
			$this->set_error('no_file_type');
			return FALSE;
		}

		$ext = strtolower(ltrim($this->file_ext, '.'));
		
		if(! in_array($ext, $this->allowed_type))
		{
			$this->set_error('is_not_allowed_type');
			return FALSE;
		}

		$image_types = array('gif', 'jpg', 'jpeg', 'png', 'jpe'); //图片类型
		
		if(in_array($ext, $image_types))
		{
			if(getimagesize($this->file_temp) === FALSE)
			{
				return FALSE;
			}
		}
		
		return TRUE;	
	}
	//检查是否允许的大小
	public function is_allowed_size()
	{
		if($this->max_size != 0 && $this->file_size > $this->max_size)
		{
			return FALSE;
		}
		else
		{
			return TRUE;
		}
	}
	
	public function validate_upload_path()
	{
		if($this->upload_path == '')
		{
			$this->set_error('no_upload_path');
			return FALSE;
		}
		
		if (function_exists('realpath') AND @realpath($this->upload_path) !== FALSE)
		{
			$this->upload_path = str_replace("\\", "/", realpath($this->upload_path));
		}
		
		if(! @is_dir($this->upload_path))  //判断是否是目录
		{
			$this->set_error('error_upload_path');
			return FALSE;
		}
		
		if(! $this->is_really_writable($this->upload_path)) 
		{
			$this->set_error('upload_path_is_not_writable');
			return FALSE;
		}
		
		$this->upload_path = preg_replace("/(.+?)\/*$/", "\\1/", $this->upload_path); //
		return TRUE;
	
	}
	
	public function get_extension($filename) // 返回扩展名
	{
		$x = explode('.', $filename);
		return '.'.end($x); //end 返回数组最后一个元素
	}
	
	// CI 中 检查 文件 或 目录 是否可写的方法
	public function is_really_writable($file)
	{
		// 在 Unix 内核系统中关闭了 safe_mode, 可以直接使用 is_writable()
		if (DIRECTORY_SEPARATOR == '/' AND @ini_get("safe_mode") == FALSE)
		{
			return is_writable($file);
		}

		// 在 Windows 系统中打开了 safe_mode的情况
		if (is_dir($file))
		{
			$file = rtrim($file, '/').'/'.md5(mt_rand(1,100).mt_rand(1,100));
	
			if (($fp = @fopen($file, "a+")) === FALSE)
			{
				return FALSE;
			}
			
			fclose($fp);
			@chmod($file, 0777);
			@unlink($file);
			return TRUE;
		}
		elseif ( ! is_file($file) OR ($fp = @fopen($file, "c+")) === FALSE)
		{
			return FALSE;
		}
		
		fclose($fp);
		return TRUE;
	}
	
	protected function _file_mime_type($file)
	{
		$regexp = '/^([a-z\-]+\/[a-z0-9\-\.\+]+)(;\s.+)?$/';

		if (function_exists('finfo_file'))
		{
			$finfo = finfo_open(FILEINFO_MIME);
			if (is_resource($finfo))
			{
				$mime = @finfo_file($finfo, $file['tmp_name']);
				finfo_close($finfo);

				if (is_string($mime) && preg_match($regexp, $mime, $matches))
				{
					$this->file_type = $matches[1];
					return;
				}
			}
		}

		if (DIRECTORY_SEPARATOR !== '\\')
		{
			$cmd = 'file --brief --mime ' . escapeshellarg($file['tmp_name']) . ' 2>&1';

			if (function_exists('exec'))
			{
				$mime = @exec($cmd, $mime, $return_status);
				if ($return_status === 0 && is_string($mime) && preg_match($regexp, $mime, $matches))
				{
					$this->file_type = $matches[1];
					return;
				}
			}

			if ( (bool) @ini_get('safe_mode') === FALSE && function_exists('shell_exec'))
			{
				$mime = @shell_exec($cmd);
				if (strlen($mime) > 0)
				{
					$mime = explode("\n", trim($mime));
					if (preg_match($regexp, $mime[(count($mime) - 1)], $matches))
					{
						$this->file_type = $matches[1];
						return;
					}
				}
			}
			
			if (function_exists('popen'))
			{
				$proc = @popen($cmd, 'r');
				if (is_resource($proc))
				{
					$mime = @fread($proc, 512);
					@pclose($proc);
					if ($mime !== FALSE)
					{
						$mime = explode("\n", trim($mime));
						if (preg_match($regexp, $mime[(count($mime) - 1)], $matches))
						{
							$this->file_type = $matches[1];
							return;
						}
					}
				}
			}
		}

		if (function_exists('mime_content_type'))
		{
			$this->file_type = @mime_content_type($file['tmp_name']);
			if (strlen($this->file_type) > 0) 
			{
				return;
			}
		}

		$this->file_type = $file['type'];
	}

	public function clean_file_name($filename)
	{
		$bad = array(
						"<!--",
						"-->",
						"'",
						"<",
						">",
						'"',
						'&',
						'$',
						'=',
						';',
						'?',
						'/',
						"%20",
						"%22",
						"%3c",		// <
						"%253c",	// <
						"%3e",		// >
						"%0e",		// >
						"%28",		// (
						"%29",		// )
						"%2528",	// (
						"%26",		// &
						"%24",		// $
						"%3f",		// ?
						"%3b",		// ;
						"%3d"		// =
					);

		$filename = str_replace($bad, '', $filename);

		return stripslashes($filename);
	}

	public function set_filename($path, $filename)
	{
		if ($this->encrypt_name == TRUE)
		{
			mt_srand();
			$filename = md5(uniqid(mt_rand())).$this->file_ext;
		}

		if ( ! file_exists($path.$filename))
		{
			return $filename;
		}

		$filename = str_replace($this->file_ext, '', $filename);

		$new_filename = '';
		for ($i = 1; $i < 100; $i++)
		{
			if ( ! file_exists($path.$filename.$i.$this->file_ext))
			{
				$new_filename = $filename.$i.$this->file_ext;
				break;
			}
		}

		if ($new_filename == '')
		{
			$this->set_error('upload_bad_filename');
			return FALSE;
		}
		else
		{
			return $new_filename;
		}
	}
}
