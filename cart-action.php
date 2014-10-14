<?php
class Cart_Action {
	public $instanceof = null; // 读取的时候，使用这个实例可以调用购物车商品信息
	public $db_instanceof = null;
	public $goods_table = 'goods'; // 商品表名

	public function __construct() {
		$this->instanceof = new Cart();
		$this->db_instanceof = $GLOBALS['vpdb'];
	}

	public static function get_instance() {
		return new Cart_Action();
	}

	public function add_cart($goods_id, $num = 1, $spec = array()) { // 把商品添加到购物车
		$sql = "SELECT price,goods_name,stock FROM ".$this->goods_table." WHERE goods_id='$goods_id'";
		$query = $this->db_instanceof->query($sql);
    	$goods_data = $this->db_instanceof->fetchRow($query);
    	if (empty($goods_data)) return false;
		
		$stock = $goods_data['stock'];
		if ($stock <= 0) return false;
		
		$num = (int)$num;
		if ($num <=0 && $num > $stock) return false;
		
		$data = array(
           'id'      => $goods_id,
           'qty'     => $num,
           'price'   => $goods_data['price'],
           'name'    => $goods_data['goods_name'],
           'options' => $spec
        );

		return $this->instanceof->insert($data);
	}

	public function update_cart($goods_id,$rowid,$num = 1) { // 更新购物车商品
		$goods_id = (int)$goods_id;

		$sql = "SELECT price,goods_name,stock FROM ".$this->goods_table." WHERE goods_id='$goods_id'";
		$query = $this->db_instanceof->query($sql);
    	$goods_data = $this->db_instanceof->fetchRow($query);

		if (empty($goods_data)) return false;
		$stock = $goods_data['stock'];
		if ($stock <= 0) return false;
		
		$num = (int)$num;
		if ($num <=0 && $num > $stock) return false;
		
		$data = array(
			'rowid'=>$rowid,
			'qty'=>$num
		);

		return $this->instanceof->update($data);
	}

	public function get_cart_data() { // 读取购物车信息
		$cart = new Cart_Action();
		$_cart_data = array();
		if ($cart->instanceof->total_items() > 0) {
			foreach ($cart->instanceof->contents() as $items) {
				$goods_id = (int)$items['id'];
				$sql = "SELECT price,goods_name,stock,goods_img FROM ".$this->goods_table." WHERE goods_id='$goods_id'";
				$query = $this->db_instanceof->query($sql);
				$v = $this->db_instanceof->fetchRow($query);
				
				$stock = $v['stock'];
				if ($stock <= 0) $stock = 0;
				
				$options = array();
				
				$_cart_data[] = array(
				'rowid' => $items['rowid'],
				'id' => $items['id'],
				'qty' => $items['qty'],
				'price' => $items['price'],
				'name' => $items['name'],
				'options' => $options,
				'subtotal' => $items['subtotal'],
				'pic' => $v['goods_img'],
				'stock' => $stock
				);
			}
		}
		return $_cart_data;
	}
	
	public function del_cart($rowid) { // 移除购物车中指定的商品
		$data = array(
		'rowid'=>$rowid,
		'qty'=>0
		);
		return $this->instanceof->update($data);
	}
}
?>
