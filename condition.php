<?php
/**
 * CakePHPの検索条件配列を生成するクラス
 *
 * PHP Version 5.x
 */

/**
 * 利用方法
 * 
	(1) $argsに指定カラムと同名のキーが存在すれば条件に追加する
	
	// CakePHPの標準の書き方
	$datetime = date('Y-m-d H:i:s');
	$conditions = array();
	$conditions['ModelA.del_flg'] = 0;
	$conditions['ModelA.my_datetime <'] = $datetime;
	if(isset($args['age']) && $args['age'] !== ''){
		$conditions['ModelA.age'] = $args['age'];
	}
	if(isset($args['sex']) && $args['sex'] !== ''){
		$conditions['ModelA.sex'] = $args['sex'];
	}
	
	// Conditionクラスを利用時
	$datetime = date('Y-m-d H:i:s');
	$conditions = Condition::create('ModelA')
					->setParams($args)
					->set('del_flg', 0)
					->set('my_datetime <', $datetime)
					->set('age')
					->set('sex')
					->getConditions();
	
	
	(2) $argsに存在するキーを全て条件に追加する
	
	$conditions = Condition::create('ModelA')
					->setParams($args)
					->setAuto(array_keys($modelA->getColumnTypes()))
					->getConditions();
	
	
	(3) 単純なOR条件
	
	$conditions = Condition::create('ModelA')
					->setParams($args)
					->where('OR')->set('release_datetime', null)
					->where('OR')->set('release_datetime <=', $datetime)
					->getConditions();
	
	
	(4) 複数のAND条件をOR条件で繋げる
	
	$conditions = Condition::create('ModelA')
					->setParams($args)
					->and_or('joken1')->set('release_datetime', null)
					->and_or('joken1')->set('release_datetime <=', $datetime)
					->and_or('joken2')->set('finish_datetime', null)
					->and_or('joken2')->set('finish_datetime >', $datetime)
					->getConditions();
	
	
	(5) Like 検索等の文字列操作を含む場合
	
	$conditions = Condition::create('ModelA')
					->setParams($args)
					->format('{} like', '%{}%')->set('name')
					->getConditions();
	
	(6) リレーションを含む場合
	
	$conditions = Condition::create('ModelA')
					->setParams($args)
					->set('age')
					->setModel('ModelB')
					->set('sex')
					->getConditions();
	
	// or
	
	$conditionsA = Condition::create('ModelA')
					->setParams($args)
					->set('age')
					->getConditions();
	$conditions = Condition::create('ModelB', $conditions, $args)
					->set('sex')
					->getConditions();
 */
class Condition {
	public $model = '';
	public $conditions = array();
	public $params = array();
	public $sets = array();
	public $pointer;
	public $key_format = '';
	public $value_format = '';
	private $__condition_key = array('');
	
/**
 * Constructor
 */
	public function __construct($model = null, $conditions = null, $params = null){
		if($model)      $this->setModel($model);
		if($conditions) $this->setConditions($conditions);
		if($params)     $this->setParams($params);
		$this->pointer =& $this->conditions;
		return $this;
	}
	
	// new instance
	public static function create(){
		$args = func_get_args();
		$class = new ReflectionClass(__CLASS__);
		return $class->newInstanceArgs($args); 
	}
	
/**
 * Setter
 */
	public function setModel($value) {
		if(is_object($value)){
			$value = $value->alias;
		}
		$this->model = $value;
		return $this;
	}
	public function setCondition($value) {
		return $this->setConditions($value);
	}
	public function setConditions($value) {
		$this->conditions = $value;
		$this->pointer =& $this->conditions;
		return $this;
	}
	public function setParams($value) {
		$this->params = $value;
		return $this;
	}
	public function addParams($params) {
		$this->params = array_merge($this->params, $params);
		return $this;
	}
	
/**
 * Getter
 */
	public function getCondition() {
		return $this->conditions;
	}
	public function getConditions() {
		return $this->conditions;
	}
	public function getParams() {
		return $this->params;
	}
 
/**
 * Set column condition
 */
	public function set($col=null, $val=null) {
		$numargs = func_num_args();
		$key = $this->_makeKey($col);
		switch($numargs){
			case 2: // 値を明示的に指定
				list($key, $val) = $this->_setFormat($key, $val);
				$this->pointer[$key] = $val;
				$this->sets[$key] = 1;
				break;
			
			case 1: // 値はパラメータのキーから自動で取得
				if(($val = $this->_getParamsData($col)) !== false){
					list($key, $val) = $this->_setFormat($key, $val);
					$this->pointer[$key] = $val;
				}
				$this->sets[$key] = 1;
				break;
		}
		$this->pointer =& $this->conditions;
		$this->key_format = '';
		$this->value_format = '';
		return $this;
	}

	public function setQuery($query='') {
		$this->pointer[] = $query;
		$this->__condition_key[] = $query;
		$this->sets[$query] = 1;
		
		$this->pointer =& $this->conditions;
		$this->key_format = '';
		$this->value_format = '';
		return $this;
	}
	
/**
 * where condition
 */
	public function where() {
		$arg = func_get_args();
		$numargs = func_num_args();
		if($numargs == 0){
			$arg = array('');
		}
		foreach($arg as $v){
			if($v == ''){
				$v = count($this->pointer);
			}
			if(!isset($this->pointer[$v])){
				$this->pointer[$v] = array();
			}
			$this->pointer =& $this->pointer[$v];
		}
		return $this;
	}
	
/**
 * where A or B or C
 */	
	public function or_or() {
		return $this->where('OR');
	}
	
/**
 * where (A or B or C) and (D or E or F)
 */	
	public function and_or($condition_key = '__and_or') {
		
		if(!in_array($condition_key, $this->__condition_key)){
			$this->__condition_key[] = $condition_key;
		}
		$flip = array_flip($this->__condition_key);
		$idx  = $flip[$condition_key];
		
		return $this->where($idx, 'OR', '');
	}
	
/**
 * where (A and B and C) or (D and E and F)
 */	
	public function or_and($condition_key = '__or_and') {
		
		if(!in_array($condition_key, $this->__condition_key)){
			$this->__condition_key[] = $condition_key;
		}
		$flip = array_flip($this->__condition_key);
		$idx  = $flip[$condition_key];
		
		return $this->where('OR', $idx, '');
	}
	
/**
 * 文字列処理(sprintf)
 */
	public function format($key_format='', $value_format='') {
		$this->key_format = $key_format;
		$this->value_format = $value_format;
		return $this;
	}
	
/**
 * 自動でconditionsを設定
 */	
	public function setAuto($cols) {
		foreach($cols as $col){
			$key = $this->_makeKey($col);
			if(!isset($this->sets[$key])) $this->set($col);
		}
		return $this;
	}
	
/**
 * 条件用パラメータの取得
 */
	protected function _getParamsData($col) {
		$params =& $this->params;
		$model  =& $this->model;
		if(substr_count($col, ' ') == 1){
			list($col, $hoge) = explode(' ', $col, 2);
		}
		if(isset($params[$model]) && isset($params[$model][$col]) && $params[$model][$col] !== ''){
			return $params[$model][$col];
		}
		if(isset($params[$col]) && $params[$col] !== ''){
			return $params[$col];
		}
		return false;
	}
	
/**
 * sprintf convert
 */
	protected function _setFormat($key, $val) {
		$replace = array(
			'%'  => '%%',
			'{}' => '%s',
		);
		if($this->key_format != ''){
			$key = sprintf(strtr($this->key_format, $replace), $key);
		}
		if($this->value_format != ''){
			$val = sprintf(strtr($this->value_format, $replace), $val);
		}
		return array($key, $val);
	}
	
/**
 * add ModelName
 */
	function _makeKey($col) {
		if(strstr($col, '.')){
			$key = $col;
		}else{
			$key = $this->model.'.'.$col;
		}
		return $key;
	}
}


