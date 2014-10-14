<?php
/**
 * 购物车程序 Modified by CodeIgniter
 *
 */
class Cart {

	// 对产品ID和产品名称进行正则验证属性
	var $product_id_rules	= '\.a-z0-9_-';
	var $product_name_rules	= '\.\:\-_ a-z0-9'; // 考虑到汉字，该功能暂不使用

	// 私有变量
	var $_cart_contents	= array();


	/**
	 * 构造方法
	 *
	 */
	public function __construct()
	{
		if ($this->session('cart_contents') !== FALSE)
		{
			$this->_cart_contents = $this->session('cart_contents');
		}
		else
		{
			// 初始化数据
			$this->_cart_contents['cart_total'] = 0;
			$this->_cart_contents['total_items'] = 0;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * 添加到购物车
	 *
	 * @access	public
	 * @param	array
	 * @return	bool
	 */
	function insert($items = array())
	{
		// 检测数据是否正确
		if ( ! is_array($items) OR count($items) == 0)
		{
			return FALSE;
		}

		// 可以添加一个商品（一维数组），也可以添加多个商品（二维数组）

		$save_cart = FALSE;
		if (isset($items['id']))
		{
			if ($this->_insert($items) == TRUE)
			{
				$save_cart = TRUE;
			}
		}
		else
		{
			foreach ($items as $val)
			{
				if (is_array($val) AND isset($val['id']))
				{
					if ($this->_insert($val) == TRUE)
					{
						$save_cart = TRUE;
					}
				}
			}
		}
		// 更新数据
		if ($save_cart == TRUE)
		{
			$this->_save_cart();
			return TRUE;
		}

		return FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * 处理插入购物车数据
	 *
	 * @access	private
	 * @param	array
	 * @return	bool
	 */
	function _insert($items = array())
	{
		// 检查购物车
		if ( ! is_array($items) OR count($items) == 0)
		{
			return FALSE;
		}

		// --------------------------------------------------------------------

		/* 前四个数组索引 (id, qty, price 和name) 是 必需的。
		   如果缺少其中的任何一个，数据将不会被保存到购物车中。
		   第5个索引 (options) 是可选的。当你的商品包含一些相关的选项信息时，你就可以使用它。
		   请使用一个数组来保存选项信息。注意：$data['price'] 的值必须大于0 
		   如：$data = array(
               'id'      => 'sku_123ABC',
               'qty'     => 1,
               'price'   => 39.95,
               'name'    => 'T-Shirt',
               'options' => array('Size' => 'L', 'Color' => 'Red')
            );
		*/

		if ( ! isset($items['id']) OR ! isset($items['qty']) OR ! isset($items['price']) OR ! isset($items['name']))
		{
			return FALSE;
		}

		// --------------------------------------------------------------------

		// 数量验证，不是数字替换为空
		$items['qty'] = trim(preg_replace('/([^0-9])/i', '', $items['qty']));
		// 数量验证
		$items['qty'] = trim(preg_replace('/(^[0]+)/i', '', $items['qty']));

		// 数量必须是数字或不为0
		if ( ! is_numeric($items['qty']) OR $items['qty'] == 0)
		{
			return FALSE;
		}

		// --------------------------------------------------------------------

		// 产品ID验证
		if ( ! preg_match("/^[".$this->product_id_rules."]+$/i", $items['id']))
		{
			return FALSE;
		}

		// --------------------------------------------------------------------

		// 验证产品名称，考虑到汉字，暂不使用
		/*
		if ( ! preg_match("/^[".$this->product_name_rules."]+$/i", $items['name']))
		{
			return FALSE;
		}
		*/

		// --------------------------------------------------------------------

		// 价格验证
		$items['price'] = trim(preg_replace('/([^0-9\.])/i', '', $items['price']));
		$items['price'] = trim(preg_replace('/(^[0]+)/i', '', $items['price']));

		// 验证价格是否是数值
		if ( ! is_numeric($items['price']))
		{
			return FALSE;
		}

		// --------------------------------------------------------------------

		// 属性验证，如果属性存在，属性值+产品ID进行加密保存在$rowid中
		if (isset($items['options']) AND count($items['options']) > 0)
		{
			$rowid = md5($items['id'].implode('', $items['options']));
		}
		else
		{
			// 没有属性时直接对产品ID加密
			$rowid = md5($items['id']);
		}
		
		// 检测购物车中是否有该产品，如果有，在原来的基础上加上本次新增的商品数量
		$_contents = $this->_cart_contents;
		$_tmp_contents = array();
		if (count($_contents)) {
			foreach ($_contents as $val)
			{
				if (is_array($val) AND isset($val['rowid']) AND isset($val['qty']) AND $val['rowid']==$rowid)
				{
					$_tmp_contents[$val['rowid']]['qty'] = $val['qty'];
				} else {
					$_tmp_contents[$val['rowid']]['qty'] = 0;
				}
			}
		}
		// --------------------------------------------------------------------

		// 清除原来的数据
		unset($this->_cart_contents[$rowid]);

		// 重新赋值
		$this->_cart_contents[$rowid]['rowid'] = $rowid;

		// 添加新项目
		foreach ($items as $key => $val)
		{
			if ($key=='qty' && isset($_tmp_contents[$rowid][$key])) {
				$this->_cart_contents[$rowid][$key] = $val+$_tmp_contents[$rowid][$key];
			} else {
				$this->_cart_contents[$rowid][$key] = $val;
			}
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * 更新购物车
	 * 
	 * @access	public
	 * @param	array
	 * @param	string
	 * @return	bool
	 */
	function update($items = array())
	{
		// 验证
		if ( ! is_array($items) OR count($items) == 0)
		{
			return FALSE;
		}

		$save_cart = FALSE;
		if (isset($items['rowid']) AND isset($items['qty']))
		{
			if ($this->_update($items) == TRUE)
			{
				$save_cart = TRUE;
			}
		}
		else
		{
			foreach ($items as $val)
			{
				if (is_array($val) AND isset($val['rowid']) AND isset($val['qty']))
				{
					if ($this->_update($val) == TRUE)
					{
						$save_cart = TRUE;
					}
				}
			}
		}

		if ($save_cart == TRUE)
		{
			$this->_save_cart();
			return TRUE;
		}

		return FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * 处理更新购物车
	 *
	 * @access	private
	 * @param	array
	 * @return	bool
	 */
	function _update($items = array())
	{
		if ( ! isset($items['qty']) OR ! isset($items['rowid']) OR ! isset($this->_cart_contents[$items['rowid']]))
		{
			return FALSE;
		}

		// 检测数量
		$items['qty'] = preg_replace('/([^0-9])/i', '', $items['qty']);

		if ( ! is_numeric($items['qty']))
		{
			return FALSE;
		}

		if ($this->_cart_contents[$items['rowid']]['qty'] == $items['qty'])
		{
			return FALSE;
		}

		if ($items['qty'] == 0)
		{
			unset($this->_cart_contents[$items['rowid']]);
		}
		else
		{
			$this->_cart_contents[$items['rowid']]['qty'] = $items['qty'];
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * 保存购物车到Session里
	 *
	 * @access	private
	 * @return	bool
	 */
	function _save_cart()
	{
		unset($this->_cart_contents['total_items']);
		unset($this->_cart_contents['cart_total']);

		$total = 0;
		$items = 0;
		foreach ($this->_cart_contents as $key => $val)
		{
			if ( ! is_array($val) OR ! isset($val['price']) OR ! isset($val['qty']))
			{
				continue;
			}

			$total += ($val['price'] * $val['qty']);
			$items += $val['qty'];

			$this->_cart_contents[$key]['subtotal'] = ($this->_cart_contents[$key]['price'] * $this->_cart_contents[$key]['qty']);
		}

		$this->_cart_contents['total_items'] = $items;
		$this->_cart_contents['cart_total'] = $total;

		if (count($this->_cart_contents) <= 2)
		{
			$this->session('cart_contents', array());

			return FALSE;
		}
		$this->session('cart_contents',$this->_cart_contents);

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * 购物车中的总计金额
	 *
	 * @access	public
	 * @return	integer
	 */
	function total()
	{
		return @$this->_cart_contents['cart_total'];
	}

	// --------------------------------------------------------------------

	/**
	 * 购物车中总共的项目数量
	 *
	 *
	 * @access	public
	 * @return	integer
	 */
	function total_items()
	{
		return @$this->_cart_contents['total_items'];
	}

	// --------------------------------------------------------------------

	/**
	 * 购物车中所有信息的数组
	 *
	 * 返回一个包含了购物车中所有信息的数组
	 *
	 * @access	public
	 * @return	array
	 */
	function contents()
	{
		$cart = $this->_cart_contents;

		unset($cart['total_items']);
		unset($cart['cart_total']);

		return $cart;
	}

	// --------------------------------------------------------------------

	/**
	 * 购物车中是否有特定的列包含选项信息
	 *
	 * 如果购物车中特定的列包含选项信息，本函数会返回 TRUE(布尔值)，本函数被设计为与 contents() 一起在循环中使用
	 *
	 * @access	public
	 * @return	array
	 */
	function has_options($rowid = '')
	{
		if ( ! isset($this->_cart_contents[$rowid]['options']) OR count($this->_cart_contents[$rowid]['options']) === 0)
		{
			return FALSE;
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * 以数组的形式返回特定商品的选项信息
	 *
	 * 本函数被设计为与 contents() 一起在循环中使用
	 *
	 * @access	public
	 * @return	array
	 */
	function product_options($rowid = '')
	{
		if ( ! isset($this->_cart_contents[$rowid]['options']))
		{
			return array();
		}

		return $this->_cart_contents[$rowid]['options'];
	}

	// --------------------------------------------------------------------

	/**
	 * 格式化数值
	 *
	 * 返回格式化后带小数点的值（小数点后有2位），一般价格使用
	 *
	 * @access	public
	 * @return	integer
	 */
	function format_number($n = '')
	{
		if ($n == '')
		{
			return '';
		}

		$n = trim(preg_replace('/([^0-9\.])/i', '', $n));

		return number_format($n, 2, '.', ',');
	}

	// --------------------------------------------------------------------

	/**
	 * 销毁购物车
	 *
	 * 这个函数一般是在处理完用户订单后调用
	 *
	 * @access	public
	 * @return	null
	 */
	function destroy()
	{
		unset($this->_cart_contents);

		$this->_cart_contents['cart_total'] = 0;
		$this->_cart_contents['total_items'] = 0;

		$this->session('cart_contents', array());
	}
	
	// --------------------------------------------------------------------

	/**
	 * 保存Session
	 *
	 * 须有session_start();
	 *
	 * @access	private
	 * @return	bool
	 */
	function session($name = 'cart_contents',$value = '') {
		if ($name=='') $name = 'cart_contents';
		if ($value === '') {
			return @$_SESSION[$name];
		} else {
			$_SESSION[$name] = $value;
		}
	}
}
?>
