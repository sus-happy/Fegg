<?php
/**
 * MY_Applicationクラス
 * 
 * 拡張Applicationクラス
 * 
 * @access public
 * @author lionheart.co.jp
 * @version 1.0.0
 */

class MY_Application extends Application
{
    protected $_model = NULL;

    /**
     *  constructor
     */
    function __construct()
    {
        parent::__construct();

        // 拡張ファイルを読み込む
        require( FEGG_DIR.'/Model.php' );
        require( FEGG_DIR.'/Modifire.php' );
    }

    /**
     * モデル読込
     */
    public function loadModel( $file )
    {
        $segments = explode('/', $file);
        $tempPath = '';
        $fileName = '';
        $nameSpace = 'Model_';
        $className = '';

        // パラメータ
        $parameter = func_get_args();
        // 頭は取り除く
        array_shift( $parameter );
       
        foreach ($segments as $key => $value) {

            // 同一階層に同一のフォルダ名とファイル名が存在する場合はファイルを優先する
            if (file_exists(FEGG_CODE_DIR . '/model/' . $tempPath . ucwords($value) . '.php')) {
                $fileName = ucwords($value);
                break;
            }
            $tempPath .= $value . '/';
            $nameSpace .= ucwords($value) . '_';
        }
        
        if ($fileName) {
            require_once(FEGG_CODE_DIR . "/model/$file.php");
            $className = $nameSpace . $fileName;

            if( func_num_args() <= 1 ) {
                return new $className;
            } else {
                $reflection = new ReflectionClass( $className );
                return $reflection->newInstanceArgs( $parameter );
            }
        } else {
            return null;
        }
    }
}
/* End of file MY_Application.php */