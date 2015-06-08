<?php
/*
 * Modelクラス
 * 
 * @access public
 * @author lionheart.co.jp
 * @version 1.0.0
 */

class Model
{

    protected
            $_table    = '',
            $_id       = NULL,
            $_visible  = NULL,
            $_app      = NULL,
            $_db       = NULL,
            $_is_admin = NULL,
            $_pager    = array(),
            $_bind     = array();

    /**
     * コンストラクタ
     *
     * @param boolean $is_admin 管理フラグ（visibleがfalseでも表示する）
     */
    public function __construct( $is_admin = FALSE )
    {
        // アプリケーションオブジェクト
        $this->_app      = &FEGG_getInstance();
        // DBクラス
        $this->_db       = $this->_app->getClass( 'DB_pdo' );
        // 管理フラグ
        $this->_is_admin = $is_admin;
    }

    /**
     * テーブル名を取得
     *
     * @return string テーブル名
     */
    public function getTableName()
    {
        return $this->_table;
    }

    /**
     * プライマリーキー名を取得
     *
     * @return string プライマリーキー名
     */
    public function getPrimary()
    {
        return $this->_id;
    }

    /**
     * 表示フラグ名を取得
     *
     * @return string 表示フラグ名
     */
    public function getVisible()
    {
        return $this->_visible;
    }

    /**
     * 指定件数のデータ取得
     *
     * @param  string  $item     取得カラム名（カンマ区切り）
     * @param  integer $per_page 1ページ毎の表示数
     * @param  integer $page     現在ページ
     * @param  string  $where    検索クエリ（?を入れておくと$dataから参照）
     * @param  mixed   $data     検索クエリのパラメータ
     * @param  string  $order    並び順
     * @param  string  $join     テーブル結合クエリ
     * @param  string  $dur      テーブル結合方向
     * @param  string  $on       テーブル結合条件クエリ
     * @param  mixed   $on_data  テーブル結合条件クエリのパラメータ
     * @return array             取得データ
     */
    public function find( $item = '*', $per_page = 10, $page = 1, $where = NULL, $data = NULL, $order = NULL, $join = NULL, $dur = NULL, $on = NULL, $on_data = NULL )
    {
        // 初期化フラグを停止しておく
        $this->_db->unsetInitQueryFlag();

        /**
         * 件数を取得
         */

        // 取得カラムを指定
        $this->_db->setItem( $item );

        // カウント用の検索条件を指定
        $this->_setWhere( $where, $data );

        // 表示フラグがTRUEのレコードだけ取得する
        // 管理フラグがFALSE、かつ、$this->_visibleが指定されている時のみ追加
        if(! $this->_is_admin && ! empty( $this->_visible ) ) {
            $this->_db->setWhere( 'AND ' . $this->_visible . ' = ?', '1' );
        }

        // テーブル結合
        $this->_setJoin( $join, $dur, $on, $on_data );

        // 件数
        $record = $this->_db->count( $this->_table )->execute()->one();
        $maxPage = ceil( $record['number_of_records'] / $per_page );

        /**
         * 以降は一覧取得
         */

        // ページャー計算
        $this->_pager['current_page'] = is_numeric($page) && 0 < $page && $page <= $maxPage ? $page : 1;
        $this->_pager['page_min'] = $this->_pager['current_page'] - 4 > 0 ? $this->_pager['current_page'] - 4 : 1;
        $this->_pager['page_max'] = $this->_pager['current_page'] + 4 <= $maxPage ? $this->_pager['current_page'] + 4 : $maxPage;
        if ($this->_pager['current_page'] > 1) {
            $this->_pager['previous_page'] = $this->_pager['current_page'] - 1;
        }
        if ($this->_pager['current_page'] < $maxPage) {
            $this->_pager['next_page'] = $this->_pager['current_page'] + 1;
        }
        $this->_pager['last_page'] = $maxPage;
        $this->_pager['number_of_records'] = $record['number_of_records'];

        // 件数指定
        $this->_db->setLimit( $per_page, $per_page*( $this->_pager['current_page']-1 ) );

        // 表示順指定
        if(! empty( $order ) ) {
            $this->_db->setOrder( $order );
        }

        // 初期化フラグを戻しておく
        $this->_db->setInitQueryFlag();

        // データ取得
        return $this->_db->select( $this->_table )->execute()->all();
    }

    /**
     * ページャーの情報を取得
     * findを実行するとデータが入る
     *
     * @return array ページャーの情報
     */
    public function getPager()
    {
        return $this->_pager;
    }

    /**
     * データ一件取得
     *
     * @param  string $item    取得カラム名（カンマ区切り）
     * @param  string $where   検索クエリ（?を入れておくと$dataから参照）
     * @param  mixed  $data    検索クエリのパラメータ
     * @param  string $order   並び順
     * @param  string $join    テーブル結合クエリ
     * @param  string $dur     テーブル結合方向
     * @param  string $on      テーブル結合条件クエリ
     * @param  mixed  $on_data テーブル結合条件クエリのパラメータ
     * @return array         取得データ
     */
    public function one( $item = '*', $where = NULL, $data = NULL, $order = NULL, $join = NULL, $dur = NULL, $on = NULL, $on_data = NULL )
    {
        // 取得カラムを指定
        $this->_db->setItem( $item );

        // 検索条件を指定
        $this->_setWhere( $where, $data );

        // 表示フラグがTRUEのレコードだけ取得する
        // 管理フラグがFALSE、かつ、$this->_visibleが指定されている時のみ追加
        if(! $this->_is_admin && ! empty( $this->_visible ) ) {
            $this->_db->setWhere( 'AND ' . $this->_visible . ' = ?', '1' );
        }

        // テーブル結合
        $this->_setJoin( $join, $dur, $on, $on_data );

        // 1件取得
        $this->_db->setLimit( 1, 0 );

        // 表示順指定
        if(! empty( $order ) ) {
            $this->_db->setOrder( $order );
        }

        // データ取得
        return $this->_db->select( $this->_table )->execute()->one();
    }

    /**
     * IDからデータ一件取得
     *
     * @param  integer $id 指定ID
     * @return array       取得データ
     */
    public function id( $id )
    {
        return $this->one( '*', $this->_id.' = ?', $id );
    }

    /**
     * データ登録
     *
     * @param  string $item カラム名
     * @param  mixed  $data 登録するデータ
     * @return integer      登録ID
     */
    public function insert( $item, $data )
    {
        // データ変換
        $this->dataBind( $data );
        // データ登録
        $this->_db->setItem( $item, $data )->insert( $this->_table )->execute();
        return $this->_db->getLastIndexId();
    }

    /**
     * データ更新
     *
     * @param string $item    カラム名
     * @param mixed  $data    登録するデータ
     * @param string $where   検索クエリ（?を入れておくと$dataから参照）
     * @param mixed  $wh_data 検索クエリのパラメータ
     */
    public function update_where( $item, $data, $where, $wh_data )
    {
        // データ変換
        $this->dataBind( $data );
        // 検索条件を指定
        $this->_setWhere( $where, $wh_data );
        // データ登録
        $this->_db->setItem( $item, $data )->update( $this->_table )->execute();
    }

    /**
     * IDを指定して更新
     *
     * @param string $item カラム名
     * @param mixed  $data 登録するデータ
     * @param string $id   指定ID
     */
    public function update( $item, $data, $id ) {
        $this->update_where( $item, $data, $this->_id.' = ?', $id );
    }

    /**
     * データ削除
     *
     * @param string $where 検索クエリ（?を入れておくと$dataから参照）
     * @param mixed  $data  検索クエリのパラメータ
     */
    public function delete_where( $where, $data )
    {
        // 検索条件を指定
        $this->_setWhere( $where, $data );
        // データ削除
        $this->_db->setItem( 'valid', array( 'valid' => 0 ) )->update( $this->_table )->execute();
    }

    /**
     * IDを指定して削除
     *
     * @param string $id 指定ID
     */
    public function delete( $id ) {
        $this->delete_where( $this->_id.' = ?', $id );
    }

    /**
     * データを物理的に削除
     *
     * @param string $where 検索クエリ（?を入れておくと$dataから参照）
     * @param mixed  $data  検索クエリのパラメータ
     */
    public function erase_where( $where, $data )
    {
        // 検索条件を指定
        $this->_setWhere( $where, $data );
        // データ削除
        $this->_db->delete( $this->_table )->execute();
    }

    /**
     * IDを指定して物理的に削除
     *
     * @param string $id 指定ID
     */
    public function erase( $id ) {
        $this->delete_where( $this->_id.' = ?', $id );
    }

    /**
     * 指定されたデータ形式に変換
     *
     * @param array $data 変換データ
     */
    protected function dataBind( &$data )
    {
        foreach ( $this->_bind as $key => $val ) {
            if( isset( $data[ $key ] ) ) {
                switch( $val ) {
                    case 'FLOAT':
                        $data[ $key ] = (float)$data[ $key ];
                        break;
                    case 'INTEGER':
                        $data[ $key ] = (int)$data[ $key ];
                        break;
                    case 'DATE':
                        if( is_numeric( $data[ $key ] ) ) {
                            $data[ $key ] = date( 'Y-m-d', $data[ $key ] );
                        } else {
                            $data[ $key ] = date( 'Y-m-d', strtotime( $data[ $key ] ) );
                        }
                        break;
                    case 'DATETIME':
                        if( is_numeric( $data[ $key ] ) ) {
                            $data[ $key ] = date( 'Y-m-d H:i:s', $data[ $key ] );
                        } else {
                            $data[ $key ] = date( 'Y-m-d H:i:s', strtotime( $data[ $key ] ) );
                        }
                        break;
                }
            }
        }
    }

    /**
     * WHERE句生成ヘルパ
     *
     * @param  string  $where    検索クエリ（?を入れておくと$dataから参照）
     * @param  mixed   $data     検索クエリのパラメータ
     */
    private function _setWhere( $where = NULL, $data = NULL )
    {
        if(! empty( $where ) ) {
            if(! empty( $data ) ) {
                if(! is_array( $data ) ) {
                    $this->_db->setWhere( $where, $data );
                } else {
                    array_unshift( $data, $where );
                    call_user_func_array( array( $this->_db, 'setWhere' ), $data );
                }
            } else {
                $this->_db->setWhere( $where );
            }
        } else {
            $this->_db->setWhere( '1 = 1' );
        }
    }

    /**
     * ON句生成ヘルパ
     *
     * @param  string $join    テーブル結合クエリ
     * @param  string $dur     テーブル結合方向
     * @param  string $on      テーブル結合条件クエリ
     * @param  mixed  $on_data テーブル結合条件クエリのパラメータ
     */
    private function _setJoin( $join = NULL, $dur = NULL, $on = NULL, $on_data = NULL )
    {
        if(! empty( $join ) ) {
            $this->_db->setJoin( $join, $dur );

            if(! empty( $on ) ) {
                if(! empty( $on_data ) ) {
                    if(! is_array( $on_data ) ) {
                        $this->_db->setOn( $on, $on_data );
                    } else {
                        array_unshift( $on_data, $on );
                        call_user_func_array( array( $this->_db, 'setOn' ), $on_data );
                    }
                } else {
                    $this->_db->setOn( $on );
                }
            }
        }
    }

    public function debug()
    {
        echo $this->_db->getLastQuery();
    }
}