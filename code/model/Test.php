<?php

/*
 * Model_Testクラス
 * 
 * @access public
 * @author lionheart.co.jp
 * @version 1.0.0
 */

class Model_Test extends Model
{
    // テーブル指定
    protected
            // テーブル名
            $_table   = 'test_table',
            // プライマリーキー
            $_id      = 'id',
            // 表示・非表示フラグ
            // is_adminフラグがfalseの場合は、表示・非表示フラグがtrueのレコードを取得する
            $_visible = 'visible',
            // 文字列以外のデータを自動変換する設定
            $_bind    = array(
                'id'        => 'INTEGER',
                'post_date' => 'DATETIME',
            );

}
