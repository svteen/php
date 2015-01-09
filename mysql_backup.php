<?php
class MysqlBackupAndRestore {

    private $config = [
        'host' => '127.0.0.1',
        'dbname'=> 'test',
        'port' => 3306,
        'username' => 'root',
        'userPassword' => '1111',
        'dbprefix' => '',
        'charset' => 'utf8',
        'path' => './protected/data/backup/',
        'isCompress' => 0,
        'isDownload' => 0,
        'limit' => 1000,
    ];
    private $content;
    private $dbh;

    const DIR_SEP = DIRECTORY_SEPARATOR; //操作系统的目录分隔符

    /**
     * 初始化相关属性
     * @param $config
     */
    public function __construct($config = null)
    {
        header("Content-type: text/html;charset=utf-8");

        if ($config !== null) {
            foreach ($this->config as $k => $v) {
                if (isset($config[$k]) && $config[$k] != '') {
                    $this->config[$k] = $config[$k];
                }
            }
        }

        $this->connect();
        $this->backup('op_message');
        //$this->recover('test_20150109144416_452909.sql');
    }

    /**
     * 连接数据库(PDO)
     */
    private function connect()
    {
        $this->dbh = new PDO('mysql:host='.$this->config['host'].';dbname='.$this->config['dbname'], $this->config['username'], $this->config['userPassword']);
        if (!$this->dbh) {
            $this->throwException('无法连接到数据库!');
        }
        $this->dbh->exec('set names utf8');
    }

    /**获取数据表,返回该数据的所有表
     * @return array
     */
    public function getTables()
    {
        $tables = array();
        $list_tables_sql = "SHOW TABLES FROM ".$this->config['dbname'].";";
        $result = $this->dbh->query($list_tables_sql)->fetchAll(constant('PDO::FETCH_NUM'));
        if($result)
            foreach ($result as $row) {
                $tables[] = $row[0];
            }
        return $tables;
    }

    /**
     * 获取备份文件
     * @param $fileName
     */
    private function getFile($fileName)
    {
        $this->content = '';
        $fileName = $this->trimPath($this->config['path'] . self::DIR_SEP . $fileName);
        if (is_file($fileName)) {
            $ext = strrchr($fileName, '.');
            if ($ext == '.sql') {
                $this->content = file_get_contents($fileName);
            } elseif ($ext == '.gz') {
                $this->content = implode('', gzfile($fileName));
            } else {
                $this->throwException('无法识别的文件格式!');
            }
        } else {
            $this->throwException('文件不存在!');
        }
    }

    private function removeOldFile()
    {
        $fileName = $this->trimPath($this->config['path'] . self::DIR_SEP . $this->config['dbname'].".sql");
        if (file_exists($fileName)) {
            $rs = unlink($fileName);
            if (!$rs) {
                return false;
            }
        }
        return true;
    }

    /*
     * 备份文件
     * private
    */
    private function setFile()
    {
        //$fileName = $this->trimPath($this->config['path'] . self::DIR_SEP . $this->config['dbname'] . '_' . date('YmdHis') . '_' . mt_rand(100000,999999) .'.sql');
        $fileName = $this->trimPath($this->config['path'] . self::DIR_SEP . $this->config['dbname'].".sql");
        $path = $this->setPath($fileName);

        if ($path !== true) {
            $this->throwException("无法创建备份目录目录 '$path'");
        }

        if ($this->config['isCompress'] == 0) {
            if (!file_put_contents($fileName, $this->content, FILE_APPEND)) {
                $this->throwException('写入文件失败,请检查磁盘空间或者权限!');
            }
        } else {
            if (function_exists('gzwrite')) {
                $fileName .= '.gz';
                if ($gz = gzopen($fileName, 'wb')) {
                    gzwrite($gz, $this->content);
                    gzclose($gz);
                } else {
                    $this->throwException('写入文件失败,请检查磁盘空间或者权限!');
                }
            } else {
                $this->throwException('没有开启gzip扩展!');
            }
        }
        if ($this->config['isDownload']) {
            $this->downloadFile($fileName);
        }
    }

    /**
     * 将路径修正为适合操作系统的形式
     * @param $path
     * @return mixed
     */
    private function trimPath($path)
    {
        return str_replace(array('/', '\\', '//', '\\\\'), self::DIR_SEP, $path);
    }

    /**
     * 设置并创建目录
     * @param $fileName
     * @return bool|string
     */
    private function setPath($fileName)
    {
        $dirs = explode(self::DIR_SEP, dirname($fileName));
        $tmp = '';
        foreach ($dirs as $dir) {
            $tmp .= $dir . self::DIR_SEP;
            if (!file_exists($tmp) && !mkdir($tmp, 0777))
                return $tmp;
        }
        return true;
    }

    /*
     * 下载文件
     * @param string $fileName 路径
    */
    private function downloadFile($fileName)
    {
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($fileName));
        header('Content-Disposition: attachment; filename=' . basename($fileName));
        readfile($fileName);
    }

    /**
     * 给表名或者数据库名加上``
     * @param $str
     * @return string
     */
    private function backquote($str)
    {
        return "`{$str}`";
    }

    /**
     * 将数组按照字节数分割成小数组
     * @param $array
     * @param int $byte
     * @return array
     */
    private function chunkArrayByByte($array, $byte = 5120)
    {
        $i = 0;
        $sum = 0;
        $return = [];
        foreach ($array as $v) {
            $sum += strlen($v);
            if ($sum < $byte) {
                $return[$i][] = $v;
            } elseif ($sum == $byte) {
                $return[++$i][] = $v;
                $sum = 0;
            } else {
                $return[++$i][] = $v;
                $i++;
                $sum = 0;
            }
        }
        return $return;
    }

    /**
     * 备份数据库表
     * @param null $saveTable
     * @return bool
     */
    public function backup($saveTable = null)
    {
        $this->removeOldFile();
        $memBeforeUesd = memory_get_usage();
        $this->content = '/*' . PHP_EOL . 'Created By Yii Database backup Extension' . PHP_EOL;
        $this->content .= PHP_EOL . 'Source Server ' . $this->config['host'] . PHP_EOL;
        $this->content .= 'Source Database ' . $this->config['dbname'] . PHP_EOL;
        $this->content .= PHP_EOL . 'DATE' . date('Y-m-d H:i:s') . PHP_EOL . '*/' . PHP_EOL;
        $dbname = $this->backquote($this->config['dbname']);

        $createDbSql = $this->dbh->query("SHOW CREATE DATABASE {$dbname}")->fetch(PDO::FETCH_NUM);

        //$this->content .= PHP_EOL . "/* 创建数据库 {$dbname} */";
        //$this->content .= PHP_EOL . "/*DROP DATABASE IF EXISTS {$dbname};*/" . PHP_EOL . "/*{$createDbSql[1]};*/";

        $tables = $this->getTables($dbname);
        foreach ($tables as $table) {
            if ($saveTable !== null && $saveTable != $table) {
                continue;
            }

            $cntSql = "SELECT COUNT(*) FROM {$table}";

            $cnt = $this->dbh->query($cntSql)->fetchColumn();

            $page = ceil($cnt/$this->config['limit']);

            $table = $this->backquote($table);
            $CreateTbSql = $this->dbh->query("SHOW CREATE TABLE {$table}")->fetch(PDO::FETCH_NUM);
            //建表SQL语句
            $this->content .= PHP_EOL . PHP_EOL . "-- ----------------------------------";
            $this->content .= PHP_EOL . "/* 创建表结构 {$table} */";
            $this->content .= PHP_EOL . "-- ----------------------------------";
            $this->content .= PHP_EOL . "DROP TABLE IF EXISTS {$table};";
            $this->content .= PHP_EOL . "{$CreateTbSql[1]};" . PHP_EOL;

            $this->content .= PHP_EOL . "/* 插入数据 {$table} */";
            for ($i = 0; $i < $page; $i++) {
                $i == 0 ? $offset = 0 : $offset = $i * $this->config['limit'];
                //获取表中的数据
                $tableData = $this->dbh->query("SELECT * FROM {$table} LIMIT {$offset},".$this->config['limit'])->fetchAll(PDO::FETCH_ASSOC);
                $tableValues = [];
                foreach ($tableData as $dataRow) {
                    foreach ($dataRow as &$v) {
                        $v = "'" . addslashes($v) . "'";
                    }
                    $tableValues[] = '(' . implode(',', $dataRow) . ')';
                }
                if (is_array($tableValues) && !empty($tableValues)) {
                    $temp = $this->chunkArrayByByte($tableValues);
                    foreach ($temp as $v) {
                        $values = implode(',', $v);
                        $this->content .= PHP_EOL . "INSERT INTO {$table} VALUES {$values};";
                    }
                }
                if (!empty($this->content)) {
                    $this->setFile($this->config['dbname'].".sql");
                }
                $this->content = '';
            }
        }

        $this->content = null;
        $memAfterUesd = memory_get_usage();
        echo "内存使用:";
        echo $memAfterUesd - $memBeforeUesd;
        return true;
    }

    /**
     * 恢复数据库
     * @param $fileName
     * @return bool
     */
    public function recover($fileName)
    {
        $this->getFile($fileName);
        if (!empty($this->content)) {
            $content = explode(";", $this->content);
            foreach ($content as $sql){
                $sql = trim($sql.";");
                //空的SQL会被认为是错误的
                if (!empty($sql)) {
                    $rs = $this->dbh->prepare($sql)->execute();
                    if (!$rs) {
                        $this->throwException('恢复数据失败!');
                    }
                }
            }
        } else {
            $this->throwException('无法读取备份文件!');
        }
        return true;
    }

    /**
     * @抛出异常信息
     */
    private function throwException($error)
    {
        throw new Exception($error);
    }
}
