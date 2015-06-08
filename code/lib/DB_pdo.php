<?php
/**
 * DBクラス
 *
 * Databaseの操作に必要な処理を提供するクラス。PHP PDOに対応。
 * 関連ファイル：database_config.php
 *               database_regular_use_query.php
 *
 * @access public
 * @author Genies, Inc. / LionHeart Co., Ltd.
 * @version 0.0.2
 */

class DB_pdo
{
    private $_app;

    private $_connect;
    private $_connectFlag;
    private $_query;
    private $_parameter;
    private $_record;
    private $_returnCode;
    private $_affectedRows;

    private $_buildMode;
    private $_items;
    private $_table;
    private $_join;
    private $_joinValues;
    private $_joinInit;
    private $_where;
    private $_whereValues;
    private $_group;
    private $_order;
    private $_limit;
    private $_regularUseQueryFlag;
    private $_regularUseQueryFlagForTable;
    private $_initQueryFlag = TRUE;

    /**
     *  constructor
     */
    public function __construct()
    {
        // アプリケーションオブジェクト
        $this->_app = &FEGG_getInstance();

        // コンフィグ取得
        $this->_app->loadConfig('database_config');
        $this->_app->loadConfig('database_regular_use_query');

        // 初期化
        $this->_initQuery();
    }

    /**
     * データ取得
     * @param string $table
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function select($table)
    {
        // buildモードを登録
        $this->_table = $table;
        $this->_buildMode = 'select';

        return $this;
    }

    /**
     * データ件数カウント
     * @param string $table 指定時：各メソッドで指定された値でquery構築、省略時：setQueryメソッドによるquery設定
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function count($table)
    {
        // buildモードを登録
        $this->_table = $table;
        $this->_buildMode = 'count';

        return $this;
    }

    /**
     * データ追加
     * @param string $table
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function insert($table)
    {
        // buildモードを登録
        $this->_table = $table;
        $this->_buildMode = 'insert';

        return $this;
    }

    /**
     * データ更新
     * @param string $table
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function update($table)
    {
        // buildモードを登録
        $this->_table = $table;
        $this->_buildMode = 'update';

        return $this;
    }

    /**
     * データ削除
     * @param string $table
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function delete($table = '')
    {
        // buildモードを登録
        $this->_table = $table;
        $this->_buildMode = 'delete';

        return $this;
    }

    /**
     * 指定テーブルを初期化する
     * @param string $table
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function truncate($table)
    {
        // buildモードを登録
        $this->_table = $table;
        $this->_buildMode = 'truncate';

        return $this;
    }

    /**
     * グループ設定
     * @param string $query
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function setGroup($query)
    {
        $this->_group .= $query;

        return $this;
    }

    /**
     * 操作項目設定
     * @param string $query 複数の場合カンマ区切り
     * @param array $values 連想配列の場合は$queryで指定した項目名と一致するもの、配列の場合は左から順に値を使用
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
    */
    public function setItem($query, $parameter = '')
    {
        if ($parameter) {
            if ($this->_isHash($parameter)) {

                // パラメーターが連想配列の場合は要素名で一致させる
                $items = explode(',', $query);
                foreach ($items as $value) {
                    $value = preg_replace('/^\s*(\w+)\s*/', '$1', $value);
                    if (isset($parameter[$value])) {
                        $this->_items['"' . $value . '"'] = $parameter[$value];
                    } else {
                        $this->_items['"' . $value . '"'] = '';
                    }
                }

            } else {

                // パラメーターが配列の場合は順番に一致させる
                $items = explode(',', $query);
                foreach ($items as $key => $value) {
                    if (isset($parameter[$key])) {
                        $value = preg_replace('/^\s*(\w+)\s*/', '$1', $value);
                        $this->_items['"' . $value . '"'] = $parameter[$key];
                    } else {
                        $this->_items['"' . $value . '"'] = '';
                    }
                }

            }
        } else {
            // パラメーター省略時は項目名のみ処理
            $items = explode(',', $query);
            foreach ($items as $value) {
                $this->_items[$value] = '';
            }
        }

        return $this;
    }

    /**
     * 取得件数設定
     * @param int $limit
     * @param int $offset
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function setLimit($limit, $offset = 0)
    {
        $this->_limit = sprintf( '%d, %d', $offset, $limit );

        return $this;
    }

    /**
     * ソート順設定
     * @param string $query
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function setOrder( $query )
    {
        $this->_order .= $query;

        return $this;
    }

    /**
     * クエリー設定
     * @param string $query
     * @param array $parameter
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function setQuery( $query, $parameter = array() )
    {
        $this->_query = $query;
        $this->_parameter = $parameter;

        return $this;
    }

    /**
     * 条件式設定
     * @param string $query 条件式（パラメータ箇所は?で記述）
     * @param array $parameter 複数ある場合は変数をカンマ区切りで順に指定
     */
    public function setWhere()
    {
        // 引数取得
        $numberOfArgs = func_num_args();
        $parameters = func_get_args();

        // クエリ取得
        $query = array_shift($parameters);

        // パラメータ処理
        if ($numberOfArgs == 1) {

            // クエリのみ
            $this->_where .= ' ' . $query;

        } else {

            // パラメーターあり
            $index = 0;
            foreach ($parameters as $parameter) {
                if (!is_array($parameter)) {

                    $this->_whereValues[] = $parameter;
                    $index = $index + 1;

                } else {
                    // クエリに対するパラメータを渡す
                    $this->_fetchParam( $index, $this->_whereValues, $query, $parameter );
                }
            }
            $this->_where .= ' ' . $query;
        }

        return $this;
    }

    /**
     * JOIN句を追加
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function setJoin( $table, $type = NULL )
    {
        $type = strtoupper( $type );
        switch( $type ) {
            case 'LEFT':
            case 'INNER':
            case 'RIGHT':
                $type = ' '.$type.' JOIN ';
                break;
            default:
                $type = ' JOIN ';
                break;
        }
        $this->_join .= $type . '"' . $table . '"';

        // join開始フラグを立てる
        $this->_joinInit = TRUE;

        return $this;
    }

    /**
     * JOIN句に対するON句を追加
     */
    public function setOn()
    {
        if( empty( $this->_join ) ) {
            exit( '"setOn" is needed join query.' );
        }

        // 引数取得
        $numberOfArgs = func_num_args();
        $parameters = func_get_args();

        // クエリ取得
        $query = array_shift($parameters);

        // join開始フラグが立っている場合はON句を追加する
        if( $this->_joinInit ) {
            $this->_join .= ' ON ';
            $this->_joinInit = FALSE;
        } else {
            $this->_join .= ' ';
        }

        // パラメータ処理
        if ($numberOfArgs == 1) {

            // クエリのみ
            $this->_join .= $query;

        } else {

            // パラメーターあり
            $index = 0;
            foreach ($parameters as $parameter) {
                if (!is_array($parameter)) {

                    $this->_joinValues[] = $parameter;
                    $index = $index + 1;

                } else {
                    // クエリに対するパラメータを渡す
                    $this->_fetchParam( $index, $this->_joinValues, $query, $parameter );
                }
            }
            $this->_join .= $query;
        }

        return $this;
    }

    /**
     * 常用クエリーを設定しない
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function unsetRegularUseQuery()
    {
        $this->_regularUseQueryFlag = false;

        return $this;
    }

    /**
     * 各テーブルの常用クエリーを設定しない
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function unsetRegularUseQueryForTable()
    {
        $this->_regularUseQueryFlagForTable = false;

        return $this;
    }

    /**
     * クエリ初期化フラグを設定する
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function setInitQueryFlag()
    {
        $this->_initQueryFlag = TRUE;

        return $this;
    }

    /**
     * クエリ初期化フラグを設定しない
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function unsetInitQueryFlag()
    {
        $this->_initQueryFlag = FALSE;

        return $this;
    }

    /**
     * クエリー実行
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function execute()
    {
        if(! empty( $this->_buildMode ) ) {
            $this->_buildQuery();
        }

        // クエリ種類の判定
        if (preg_match('/^\s*select.+/i', $this->_query)) {

            // データベースが明示的に指定されていなければ Slave へ接続
            if (!$this->_connectFlag) {
                $this->slaveServer();
            }

            // クエリーを実行して、論理的に非接続状態にする
            $this->_record = $this->_fetchAll($this->_query, $this->_parameter);

        } else {

            // データベースが明示的に指定されていなければ Master へ接続
            if (!$this->_connectFlag) {
                $this->masterServer();
            }

            // クエリーを実行して、論理的に非接続状態にする
            $this->_returnCode = $this->_executeQuery($this->_query, $this->_parameter);

        }
        $this->_initQuery();

        return $this;
    }

    /**
     * クエリー生成
     * 初期化は行わない
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function compile()
    {
        if(! empty( $this->_buildMode ) ) {
            $this->_buildQuery();
        }

        return $this;
    }

    /**
     * 取得したレコードを返す
     * @param string $index 配列のキーにする項目ID
     * @return array
     */
    public function all($index = '')
    {
        if ($index) {
            $tempRecord = $this->_record;
            $record = array();
            foreach ($tempRecord as $key => $value) {
                $record[$value[$index]] = $value;
            }
        } else {
            $record = $this->_record;
        }

        return $record;
    }

    /**
     * 取得したレコードの１件目を返す
     * @return array
     */
    public function one()
    {
        if (is_array($this->_record)) {
            $record = $this->_record;
        } else {
            $record = array();
        }
        return array_shift($record);
    }


    /**
     * 指定した項目だけの配列を取得
     * @param string $index
     * @return array
     */
    public function id($index)
    {
        $tempRecord = $this->_record;
        $ids = array();
        foreach ($tempRecord as $key => $value) {
            $ids[] = $value[$index];
        }

        return $ids;
    }

    /**
     * 1次元配列での取得
     * @return array
     */
    public function simpleArray($keyName, $valueName)
    {
        $tempRecord = $this->_record;
        $record = array();
        foreach ($tempRecord as $key => $value) {
            $record[$value[$keyName]] = $value[$valueName];
        }

        return $record;
    }

    /**
     * 取得行数、結果行数の取得
     * @return integer 結果行数
     */
    public function getAffectedRow()
    {
        $this->_affectedRows;
    }

    /**
     * 直近で登録されたオートナンバーの取得
     * @return Integer 取得できなかったときは0を返す
     */
    public function getLastIndexId()
    {
        //if (!$this->_connectFlag) {
        //    $this->masterServer();
        //}
        $num = $this->_connect->lastInsertId();

        if (isset($num)) {
            return $num;
        } else {
            return 0;
        }
    }

    /**
     * 最後に実行したクエリーの取得
     */
    public function getLastQuery()
    {
        $query = str_replace('?', '%s', $this->_query);
        $query = vsprintf($query, $this->_parameter);

        return $query;
    }

    public function getLastQueryDebug()
    {
        return array( 'query' => $this->_query, 'parameter' => $this->_parameter );
    }

    /**
     * リターンコード取得
     * @return int
     */
    public function getReturnCode()
    {
        return $this->_returnCode;
    }

    /**
     * マスターデータベースへの接続
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function masterServer()
    {
        // 接続
        $this->_connect($this->_app->config['database_config']['master']['dsn'],
                        $this->_app->config['database_config']['master']['username'],
                        $this->_app->config['database_config']['master']['password']
        );
        $this->_connectFlag = true;

        return $this;
    }

    /**
     * スレーブサーバーへの接続
     * @return object メソッドチェーンに対応するため自身のオブジェクト($this)を返す
     */
    public function slaveServer()
    {
        // 接続先のサーバーを決定（ランダム）
        $maxServer = count($this->_app->config['database_config']['slave']) - 1;

        $serverNo = 0;
        if ($maxServer > 0) {
            mt_srand();
            $serverNo = mt_rand(0, $maxServer);
        }

        // 接続
        $this->_connect($this->_app->config['database_config']['slave'][$serverNo]['dsn'],
                        $this->_app->config['database_config']['slave'][$serverNo]['username'],
                        $this->_app->config['database_config']['slave'][$serverNo]['password']
        );
        $this->_connectFlag = true;

        return $this;
    }

    /**
     * クエリー構築
     * @param string $sqlType
     */
    private function _buildQuery() {

        $this->_query = '';
        $this->_parameter = array();

        // 常用クエリーの設定
        if ($this->_regularUseQueryFlag) {
            $this->_setRegularUseQuery( $this->_buildMode );
        }

        $queryType = strtoupper( $this->_buildMode );
        $query = '';
        switch ($queryType) {
            case 'COUNT';
                $query .= 'Select Count(*) as number_of_records ';
                $query .= ' From "' . $this->_table . '" ';
                $query .= isset($this->_join ) ? $this->_join.' ' : '';
                $query .= isset($this->_where) ? 'Where ' . $this->_where : '';
                $query .= isset($this->_group) ? ' Group By ' . $this->_group : '';
                $this->_query = $query;
                $this->_array_merge( $this->_parameter, $this->_joinValues, $this->_whereValues );
                break;

            case 'SELECT':
                $query .= 'Select ';
                $tempQuery = '';
                if (is_array($this->_items)) {
                    foreach($this->_items as $key => $value) {
                        if ($tempQuery) { $tempQuery .= ", "; }
                        $tempQuery .= $key;
                    }
                    $query .= $tempQuery;
                } else {
                    $query .= '*';
                }

                $query .= ' From "' . $this->_table . '" ';
                $query .= isset($this->_join ) ? $this->_join.' ' : '';
                $query .= isset($this->_where) ? 'Where ' . $this->_where : '';
                $query .= isset($this->_group) ? ' Group By ' . $this->_group : '';
                $query .= isset($this->_order) ? ' Order By ' . $this->_order : '';
                $query .= isset($this->_limit) ? ' Limit ' . $this->_limit : '';

                $this->_query = $query;
                $this->_array_merge( $this->_parameter, $this->_joinValues, $this->_whereValues );
                break;

            case 'INSERT':
                $query .= 'Insert Into "' . $this->_table . '" ';
                $tempQuery1 = '';
                $tempQuery2 = '';
                foreach($this->_items as $key => $value) {
                    if (preg_match('/([^=]+)\s*=\s*([\w\(\)\s\+]+)/i', $key, $match)) {

                        // 代入形式
                        switch (true) {
                            case (preg_match('/^now/i', $match[2])):
                                if ($tempQuery1) { $tempQuery1 .= ", "; }
                                $tempQuery1 .= $match[1];
                                if ($tempQuery2) { $tempQuery2 .= ", "; }
                                $tempQuery2 .= '?';
                                $this->_parameter[] = $this->_app->getDatetime();
                                break;

                            default:
                                if ($tempQuery1) { $tempQuery1 .= ", "; }
                                $tempQuery1 .= $match[1];
                                if ($tempQuery2) { $tempQuery2 .= ", "; }
                                $tempQuery2 .= '?';
                                $this->_parameter[] = $match[2];
                                break;
                        }

                    } else {

                        // 項目名のみ
                        if ($tempQuery1) { $tempQuery1 .= ", "; }
                        $tempQuery1 .= $key;

                        if ($tempQuery2) { $tempQuery2 .= ", "; }
                        $tempQuery2 .= '?';

                        $this->_parameter[] = $value;
                    }
                }

                $query .= '(' . $tempQuery1 . ') Values (' . $tempQuery2 . ')';

                $this->_query = $query;
                break;

            case 'UPDATE':
                $query .= 'Update "' . $this->_table . '" Set ';
                $tempQuery1 = '';
                foreach($this->_items as $key => $value) {
                    if (preg_match('/([^=]+)\s*=\s*([\w\(\)]+)/i', $key, $match)) {

                        // 代入形式
                        if ($tempQuery1) { $tempQuery1 .= ", "; }
                        $tempQuery1 .= $match[1] . '= ?';

                        switch ($match[2]) {
                            case 'now()':
                                $this->_parameter[] = $this->_app->getDatetime();
                                break;

                            default:
                                $this->_parameter[] = $match[2];
                                break;
                        }

                    } else {

                        // 項目名のみ
                        if ($tempQuery1) { $tempQuery1 .= ", "; }
                        $tempQuery1 .= $key . '= ?';

                        $this->_parameter[] = $value;
                    }
                }
                $query .= $tempQuery1 . ' ';

                $query .= $this->_where ? 'Where ' . $this->_where : '';
                foreach ($this->_whereValues as $key => $value) {
                    $this->_parameter[] = $value;
                }

                $this->_query = $query;
                break;

            case 'DELETE':
                $query = 'Delete ';
                $query .= 'From ' . $this->_table . ' ';
                $query .= $this->_where ? 'Where ' . $this->_where : '';
                foreach ($this->_whereValues as $key => $value) {
                    $this->_parameter[] = $value;
                }

                $this->_query = $query;
                break;

            case 'TRUNCATE':
                $query = 'Truncate ' . $this->_table . ' ';

                $this->_query = $query;
                break;
        }

        /**
         * 定数を変換
         * CURRENT_TABLE -> テーブル名
         */
        $this->_query = str_replace( 'CURRENT_TABLE', '"'. $this->_table .'"', $this->_query );

        $returnArray[0] = $this->_query;
        $returnArray[1] = $this->_parameter;

        return $returnArray;
    }

    /**
     * クエリに対するパラメータを渡す
     * @param array  $values
     * @param string $query
     * @param array  $parameter
     */
    private function _fetchParam( $index, &$values, &$query, $parameter )
    {
        // パラメーターが配列の場合以下の変換を行う
        // = --> in,
        // in --> カンマ区切り
        // <> --> not in
        // like --> or 区切り

        // 変換位置の確定
        preg_match_all('/(\`?\w+\`?\s*(=|<|>|<>|like|in)\s*\(?\s*\?\s*\)?)/i', $query, $matches, PREG_OFFSET_CAPTURE);
        $position = $matches[0][$index][1];

        // 演算子の確定
        preg_match_all('/(\`?\w+\`?\s*(=|<|>|<>|like|in)\s*\(?\s*\?\s*\)?)/i', $query, $matches, PREG_PATTERN_ORDER);
        $operator = $matches[2][$index];

        // 対象箇所までのクエリー取得
        $convertedQueryFrontPart = substr($query, 0, $position);
        if ($position > 0) {
            $convertedQuery = substr($query, $position);
        } else {
            $convertedQuery = $query;
        }

        // 項目名取得
        $pattern = '/^\s*\w+/i';
        preg_match($pattern, $convertedQuery, $matches);
        $itemName = $matches[0];
        $itemName = '"' . $itemName . '"';

        // 対象箇所からのクエリー取得
        $convertedQuery = preg_replace('/^\s*\w+\s*' . $operator . '\s*\(?\s*\?\s*\)?(.*)/', '$1', $convertedQuery);

        $tempQuery = '';
        $operator = strtolower($operator);
        switch ($operator) {
            case '=':
            case 'in':
                foreach ($parameter as $key => $value) {
                    if ($tempQuery) {
                        $tempQuery .= ',';
                    }
                    $tempQuery .= '?';
                    $values[] = $value;
                }
                $convertedQuery = $convertedQueryFrontPart . $itemName . ' in (' . $tempQuery . ') ' . $convertedQuery;
                break;

            case '<>':
                foreach ($parameter as $key => $value) {
                    if ($tempQuery) {
                        $tempQuery .= ',';
                    }
                    $tempQuery .= '?';
                    $values[] = $value;
                }
                $convertedQuery = $convertedQueryFrontPart . $itemName . ' not in (' . $tempQuery . ') ' . $convertedQuery;
                break;

            case 'like':
                $tempQuery = '';
                foreach ($parameter as $key => $value) {
                    if ($tempQuery) {
                        $tempQuery .= 'or ';
                    }
                    $tempQuery .= $itemName . ' Like ? ';
                    $values[] = $value;
                }
                $convertedQuery = $convertedQueryFrontPart . '(' . $tempQuery . ') ' . $convertedQuery;
                break;

        }
        $index = $index + 1;
        $query = $convertedQuery;
    }

    /**
     * DBサーバーとの接続切断
     */
    private function _close()
    {
        if ($this->_connect) {
            $this->_connect = null;
        }
    }

    /**
     * DBサーバーへの接続確立
     * @param string $dsn データソース名
     * @param string $user ユーザー
     * @param string $password パスワード
     */
    private function _connect($dsn, $user, $password)
    {
        // 接続
        try {
            $this->_connect = new PDO ( $dsn, $user, $password );
            if ( $this->_connect->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql' ) {
                $this->_connect->exec( "SET sql_mode='ANSI_QUOTES'" );
                $this->_connect->setAttribute( PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, TRUE );
            }
        } catch( PDOException $e ) {
            echo "Connection failed: " . $e->getMessage();
            exit;
        }
    }

    /**
     * エスケープ
     * @param array $data エスケープ対象値
     */
    private function _escape($data)
    {
        // 格納用に文字をエスケープ（標準関数での非対応文字は別途処理）
        foreach ($data as $key => $value) {
            $data[$key] = str_replace(array('\\', '%', '_'), array('\\\\', '\%', '\_'), $data[$key]);
            $data[$key] = $this->_connect->quote( $value );
            // $data[$key] = "'" . $data[$key] . "'";
        }
        return $data;
    }

    /**
     * クエリ実行
     * @param string $query SQL文（パラメーター部分は?で表記）
     * @param array $parameter パラメーター配列（SQL中の?の順序に合わせる）
     * @return 正常時: True / 異常時: False
     */
    private function _executeQuery($query, $parameter)
    {
        // 結果格納変数の初期化
        $this->_affectedRows = 0;
        $record = array();

        // プリペアドステートメントに登録
        $sth = $this->_connect->prepare( $query );

        // プリペアドステートメント登録結果の確認
        if (! $sth ) {
            $this->_error($query);
        }

        // パラメーターのエスケープとバインド
        if (is_array($parameter)) {
            // クエリー実行
            $result = $sth->execute( $parameter );
        } else {
            // クエリー実行
            $result = $sth->execute();
        }

        // クエリー実行
        if (! $result ) {
            $this->_error($query);
        }

        if ($result) {

            // 結果行数の格納
            $this->_affectedRows = $sth->rowCount();

        } else { $this->_error($query); }

        return $this->_affectedRows;
    }

    /**
     * データ取得
     * @param string $query SQL文（パラメーター部分は?で表記）
     * @param array $parameter パラメーター配列（SQL中の?の順序に合わせる）
     * @return array 結果を配列で返す。項目名による連想配列。
     */
    private function _fetchAll($query, $parameter)
    {
        // 結果格納変数の初期化
        $this->_affectedRows = 0;
        $record = array();

        // プリペアドステートメントに登録
        $sth = $this->_connect->prepare( $query );

        // プリペアドステートメントの結果を確認
        if (! $sth ) {
            $this->_error($query);
        }

        if (is_array($parameter)) {
            // クエリー実行
            $result = $sth->execute( $parameter );
        } else {
            // クエリー実行
            $result = $sth->execute();
        }

        // クエリー実行結果を確認
        if (! $result ) {
            $this->_error($query);
        }

        if ($result) {

            // 結果行数の格納
            $this->_affectedRows = $sth->rowCount();

            // $recordに格納
            $record = $sth->fetchAll( PDO::FETCH_ASSOC );

        } else { $this->_error($query); }

        // メモリを解放
        $sth->closeCursor();

        return $record;
    }

    /**
     * 初期化
     */
    private function _initQuery()
    {
        // 初期化フラグを確認
        if(! $this->_initQueryFlag ) {
            return;
        }

        // クエリー用変数
        $this->_buildMode = null;
        $this->_items = null;
        $this->_table = null;
        $this->_join  = null;
        $this->_joinValues  = null;
        $this->_joinInit = null;
        $this->_where = null;
        $this->_whereValues = null;
        $this->_group = null;
        $this->_order = null;
        $this->_limit = null;

        // 接続フラグ
        $this->_connectFlag = false;

        // 常用クエリーフラグ
        $this->_regularUseQueryFlag = true;
        $this->_regularUseQueryFlagForTable = true;
    }

    /**
     * 連想配列判定
     * @param array 判定対象の配列
     * @return true: 連想配列 false: 配列
     */
    private function _isHash($array)
    {
        // 連想配列の先頭キーに0は使えず、配列の先頭は0という前提
        reset($array);
        list($key) = each($array);

        return $key !== 0;
    }

    /**
     * 配列合成
     * via: http://php.net/manual/ja/function.array-push.php#107995
     */
    private function _array_merge( &$array )
    {
        $numArgs = func_num_args();
        if( 2 > $numArgs ) {
            trigger_error(sprintf('%s: expects at least 2 parameters, %s given', __FUNCTION__, $numArgs), E_USER_WARNING);
            return false;
        }

        // 追加予定の変数が配列じゃなかったら配列にする
        if(! is_array( $array ) ) {
            if(! empty( $array ) ) {
                $array = array( $array );
            } else {
                $array = array();
            }
        }

        // 追加する配列群
        $values = func_get_args();
        array_shift( $values );

        foreach($values as $v) {
            // 配列だったらい後ろに追加
            if( is_array( $v ) ) {
                if( count( $v ) > 0 ) {
                    foreach( $v as $w ) {
                        $array[] = $w;
                    }
                }
            // 空だったら追加しない
            } else if( strlen( $v ) > 0 ) {
                $array[] = $v;
            }
        }

        return count( $array );
    }

    /**
     * 常用クエリーの設定
     * @param string $queryType
     */
    private function _setRegularUseQuery($queryType)
    {
        // テーブルに応じて付加するクエリー
        if ($this->_regularUseQueryFlagForTable) {
            // 項目
            if (isset($this->_app->config['database_regular_use_query']['table'][$this->_table][$queryType]['item']) && $this->_app->config['database_regular_use_query']['table'][$this->_table][$queryType]['item']) {
                $this->setItem($this->_app->config['database_regular_use_query']['table'][$this->_table][$queryType]['item']);
            }

            // 条件
            if (isset($this->_app->config['database_regular_use_query']['table'][$this->_table][$queryType]['where']) && $this->_app->config['database_regular_use_query']['table'][$this->_table][$queryType]['where']) {
                $conjunction = '';
                if ($this->_where) {
                    $conjunction = ' And ';
                }
                $this->setWhere($conjunction . $this->_app->config['database_regular_use_query']['table'][$this->_table][$queryType]['where']);
            }

            // 並び順
            if (isset($this->_app->config['database_regular_use_query']['table'][$this->_table][$queryType]['order']) && $this->_app->config['database_regular_use_query']['table'][$this->_table][$queryType]['order']) {
                $conjunction = '';
                if ($this->_order) {
                    $conjunction = ' ,';
                }
                $this->setOrder($conjunction . $this->_app->config['database_regular_use_query']['table'][$this->_table][$queryType]['order']);
            }
        }

        // テーブルに関わらず付加するクエリー
        if ($this->_regularUseQueryFlag) {

            // 項目
            if (isset($this->_app->config['database_regular_use_query']['regular_use'][$queryType]['item']) && $this->_app->config['database_regular_use_query']['regular_use'][$queryType]['item']) {
                $this->setItem($this->_app->config['database_regular_use_query']['regular_use'][$queryType]['item']);
            }

            // 条件
            if (isset($this->_app->config['database_regular_use_query']['regular_use'][$queryType]['where']) && $this->_app->config['database_regular_use_query']['regular_use'][$queryType]['where']) {
                $conjunction = '';
                if ($this->_where) {
                    $conjunction = ' And ';
                }
                $this->setWhere($conjunction . $this->_app->config['database_regular_use_query']['regular_use'][$queryType]['where']);
            }

            // 並び順
            if (isset($this->_app->config['database_regular_use_query']['regular_use'][$queryType]['order']) && $this->_app->config['database_regular_use_query']['regular_use'][$queryType]['order']) {
                $conjunction = '';
                if ($this->_order) {
                    $conjunction = ' ,';
                }
                $this->setOrder($conjunction . $this->_app->config['database_regular_use_query']['regular_use'][$queryType]['order']);
            }
        }
    }

    /**
     * エラー処理
     * @param string $query 実行したクエリー
     */
    private function _error($query)
    {
        if(FEGG_DEVELOPER) {
            $error = $this->_connect->errorInfo();
            echo "[Error] " . $error[2] . '<br/>';
            echo "[Query] " . $query . '<br/>';
        }
        exit;
    }
}
/* End of file: DB.php */