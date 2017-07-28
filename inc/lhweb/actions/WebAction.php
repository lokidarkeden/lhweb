<?php
namespace lhweb\actions;

use DateTime;
use lhweb\controller\AbstractController;
use lhweb\database\AbstractEntity;
use lhweb\database\LHDB;
use lhweb\exceptions\ParametroException;
use lhweb\exceptions\ParametroRequeridoException;

abstract class WebAction {
    public static $FORMATO_DATA_EXIBICAO = "d/m/Y";
    public static $FORMATO_DATAHORA_EXIBICAO = "d/m/Y H:i";
    public static $FORMATO_DATA_DB= "Y-m-d";
    public static $FORMATO_DATAHORA_DB= "Y-m-d H:i";
    
    /**
     *
     * @var LHDB
     */
    protected $db;
    
    /**
     *
     * @var AbstractController
     */
    protected $controller;
    
    /**
     *
     * @var array
     * Dados da request - $_POST e $_GET
     */
    protected $in;
    
    
    public static $PARAM_INT = "int";
    public static $PARAM_FLOAT = "float";
    public static $PARAM_STRING = "string";
    
    public abstract function buildObjectFromRequest();
    public abstract function initController();
    
    public function __construct() {
        $get  = $this->parseRequestData($_GET);
        $post = $this->parseRequestData($_POST);
        $this->in = array_merge($get,$post);
    }
    
    public function initDatabase(LHDB $db=null) {
        if($db===null){
            $this->db = LHDB::getConnection();
        } else {
            $this->db = $db;
        }
    }
    
    public function getController(){
        return $this->controller;
    }
    
    /**
     * 
     * @param array $req
     * @return array
     * Faz a leitura dos paramêtros de formulario e monta um array,
     * convertendo os arrays da request, para arrays do php.
     * Já faz o escape de HTML para evitar injeção de código.
     */
    public function parseRequestData($req){
        $ret = array();
        foreach($req as $key => $val) {
            if(is_array($val)) {
                $ret[$key] = $this->parseRequestData($val);
            } else {
                $ret[$key] = htmlspecialchars($val, ENT_QUOTES | ENT_HTML5);
            }
        }

        return $ret;
    }
    
    
    /**
     * 
     * @param type String
     * @return boolean 
     * Retorna true caso a Ação requira autenticação.
     */
    public function requireAuth($acao){
        switch($acao){
            default:
                return true;
        }
    }
    
    /**
     * 
     * @param type string
     * @return boolean
     * Must return true when the action is going the change Session values, like doing login.
     */
    public function requireWriteSession($acao){
        switch($acao){
            default: return false;
        }
    }
    
    public function setDb($db){
        $this->db = $db;
    }
    
    public function getFile($paramName){
        if(array_key_exists($paramName, $_FILES)){
            return $_FILES[$paramName];
        } else {
            return NULL;
        }
    }
    
    public function getFileName($paramName){
        if(array_key_exists($paramName, $_FILES)){
            return filter_var($_FILES[$paramName]["name"],FILTER_SANITIZE_STRING);
        } else {
            return NULL;
        }
    }
    
    public function getFileContent($paramName){
        if(array_key_exists($paramName, $_FILES)){
            return file_get_contents($_FILES[$paramName]["tmp_name"]);
        } else {
            return NULL;
        }
    }
    
    public function formatarParametroFloat($val){
        return $val;
    }
    
    /**
     * 
     * @param string $paramName
     * @param int $tipo
     * @param boolean $requerido
     * @param boolean $permitirVazio
     * @return type
     * @throws ParametroRequeridoException
     */
    private function getParametro($in, $paramName, $tipo, $requerido=false, $permitirVazio=true){
        $param = array_key_exists($paramName, $in)?$in[$paramName]:null;
        if($requerido) {
            if(!array_key_exists($paramName, $in)){
                throw new ParametroRequeridoException("O Campo $paramName é requerido");
            }
            
            if(!$permitirVazio && empty($in[$paramName])){
                throw new ParametroRequeridoException("Preencha o campo $paramName");
            }
        }
        
        switch($tipo){
            case static::$PARAM_INT   : 
                $param = filter_var($param, FILTER_SANITIZE_NUMBER_INT);
                return is_numeric($param)?$param:null;
            case static::$PARAM_FLOAT :
                $param = filter_var($this->formatarParametroFloat($param), FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION);
                return is_numeric($param)?$param:null;
            case static::$PARAM_STRING:
            default:
                $param = str_replace("\0", "", $param); // Removendo Null Byte, vetor de ataques.
                return filter_var($param,FILTER_SANITIZE_STRING);
        }
    }
    
    public function getParametroInt($paramName){
        return $this->getParametro($this->in, $paramName, static::$PARAM_INT, false, true);
    }
    
    public function getParametroFloat($paramName){
        return $this->getParametro($this->in, $paramName, static::$PARAM_FLOAT, false, true);
    }
    
    public function getParametroString($paramName){
        return $this->getParametro($this->in, $paramName, static::$PARAM_STRING, false, true);
    }
    
    public function getParametroArray($arrayName, $tipo){
        $ret = [];
        if(array_key_exists($arrayName,$this->in)) {
            foreach(array_keys($this->in[$arrayName]) as $k){
                array_push($ret, $this->getParametro($this->in[$arrayName], $k, $tipo));
            }
        }
        
        return $ret;
    }
    
    public function getParametroData($paramName){
        $txt = $this->getParametro($this->in, $paramName, static::$PARAM_STRING, false, true);
        $dt = DateTime::createFromFormat(static::$FORMATO_DATA_EXIBICAO, $txt);
        if($dt){
            return $dt;
        } else {
            return null;
        }
    }
    
    public function getParametroDataAsString($paramName){
        $txt = $this->getParametro($this->in, $paramName, static::$PARAM_STRING, false, true);
        $dt = DateTime::createFromFormat(static::$FORMATO_DATA_EXIBICAO, $txt);
        if($dt){
            return $dt->format(static::$FORMATO_DATA_DB);
        } else {
            return null;
        }
    }
    
    public function requererParametroInt($paramName, $permitirVazio=false){
        return $this->getParametro($this->in, $paramName, static::$PARAM_INT, true, $permitirVazio);
    }
    
    public function requererParametroFloat($paramName, $permitirVazio=false){
        return $this->getParametro($this->in, $paramName, static::$PARAM_FLOAT, true, $permitirVazio);
    }
    
    public function requererParametroString($paramName, $permitirVazio=false){
        return $this->getParametro($this->in, $paramName, static::$PARAM_STRING, true, $permitirVazio);
    }
    
    public function requererParametroData($paramName){
        $txt = $this->getParametro($this->in, $paramName, static::$PARAM_STRING, true);
        $dt = DateTime::createFromFormat(static::$FORMATO_DATA_EXIBICAO, $txt);
        if($dt){
            return $dt;
        } else {
            throw new ParametroException("[$paramName] Data Inválida");
        }
    }
    
    public function requererParametroDataAsString($paramName){
        $txt = $this->getParametro($this->in, $paramName, static::$PARAM_STRING, true);
        $dt = DateTime::createFromFormat(static::$FORMATO_DATA_EXIBICAO, $txt);
        if($dt){
            return $dt->format(static::$FORMATO_DATA_DB);
        } else {
            throw new ParametroException("[$paramName] Data Inválida");
        }
    }
    
    
    public function getPk(){
        return $this->getParametroInt($this->controller->getPkName());
    }
    
    /**
     * 
     * @return AbstractEntity
     */
    public function Primeiro(){
        return $this->controller->primeiro();
    }
    
    /**
     * 
     * @return AbstractEntity
     */
    public function Ultimo(){
        return $this->controller->ultimo();
    }
    
    /**
     * 
     * @return AbstractEntity
     */
    public function Anterior(){
        $pk  = $this->getPk();
        $ret = $this->controller->anterior($pk);
        if(!$ret){
            $ret = $this->controller->ultimo();
        }
        
        return $ret;
    }
    
    /**
     * 
     * @return AbstractEntity
     */
    public function Proximo(){
        $pk  = $this->getPk();
        $ret = $this->controller->proximo($pk);
        if(!$ret){
            $ret = $this->controller->primeiro();
        }
        return $ret;
    }
    
    /**
     * 
     * @return AbstractEntity
     */
    public function Mover(){
        $pk  = $this->getPk();
        return $this->controller->getByPK($pk);
    }
    
    /**
     * 
     * @return type
     * @throws RegistroNaoEncontrado
     */
    public function Apagar(){
        $pk  = $this->getPk();
        return $this->controller->apagar($pk);
    }
    
    public function Salvar(){
        $obj = $this->buildObjectFromRequest();
        return $this->controller->salvar($obj);
    }
    
    public function get($obj, $var) {
        if($obj===null) {
            return;
        }
        
        if(!property_exists($obj, $var)){
            return;
        }
        
        return htmlspecialchars($obj->$var);
    }
}
