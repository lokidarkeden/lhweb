<?php
namespace lhweb\database;
/**
 * Description of AbstractEntity
 *
 * @author loki
 */
abstract class AbstractEntity implements \JsonSerializable {
    protected static $primaryKey = "id";
    protected static $primaryKeyTipo = LHDB::PARAM_INT;
    protected static $table = null;
    
    public function __construct() {
    }
    
    /**
     *
     * @var array
     * Variável utilizada para configurar o mapeamento entre os campos da classe
     * e o nome das colunas no banco de dados. por ex:
     * protected $campos = array(
     *     "campoDaClasse" => "ColunaDoBanco"
     * );
     * Ao fazer um select, será utilizado na SQL o ColunaDoBanco, mas quando o 
     * resultado retornar será armazenado na propriedade campoDaClasse do objeto.
     * 
     */
    protected static $campos = array();
    
    /**
     *
     * @var array
     * Tipos dos campos, deve ser declarado no formato:
     * nomeDoCampo => LHDB::PARAM_STR
     */
    protected static $tipos = array();
    private $editClone;
    
    
    public static function getCampos(){
        return static::$campos;
    }
    
    /**
     * Cria uma cópia da classe atual para comprar os valores e alterar
     * somente o necessário em caso de chamada de update.
     */
    public function editMode(){
        $c = static::class;
        $this->editClone = new $c();
        
        foreach($this as $key => $val) {
            if($key == "editClone") {
                continue;
            }
            
            $this->editClone->$key = $val;
        }
    }
    
    /**
     * 
     * @return GenericQuery
     */
    public static function getBasicMoveQuery(){
        if(static::$table===null){
            $class = explode("\\",strtolower(static::class));
            static::$table = str_replace("entity", "", strtolower($class[count($class)-1]));
        }
        
        $q = LHDB::getConnection()->query(static::$table);
        
        if(count(static::$campos) > 0) {
            $q->campos(static::$campos);
        } else {
            $q->campos(array(static::$table.".*"));
        }
        
        return $q;
    }
    
    public static function getPkName(){
        return static::$table . "." . static::getNomeCampo(static::$primaryKey);
    }
    
    public static function getPkAttribute(){
        foreach(static::$campos as $key => $val) {
            if($val === static::$primaryKey) {
                return $key?$key:$val;
            }
        }
        
        return static::$primaryKey;
    }
    
    public static function getNomeCampo($campo){
        return array_key_exists($campo, static::$campos)?static::$campos[$campo]:$campo;
    }
    
    public static function getTipoCampo($campo){
        return array_key_exists($campo, static::$tipos)?static::$tipos[$campo]:LHDB::PARAM_STR;
    }
    
    /**
     * 
     * @param type $rs
     * @returns AbstractEntity
     */
    public static function makeFromRs($rs) {
        if(!$rs) {
            return NULL;
        }
        
        $c = static::class;
        $o = new $c();
        foreach($o as $key => $val){
            $campoDoBanco = static::getNomeCampo($key);
            if(is_array($rs) && array_key_exists($campoDoBanco, $rs)){
                $o->$key = $rs[$campoDoBanco];
            } else if(is_object($rs) && property_exists($campoDoBanco, $campoDoBanco)){
                $o->$key = $rs->$campoDoBanco;
            }
        }
        
        unset($o->editClone);
        return $o;
    }
    
    /**
     * 
     * @param int $pk
     * @return AbstractEntity
     */
    public static function getByPK($pk){
        $rs = static::getBasicMoveQuery()
                ->andWhere(static::getPkName())->equals($pk, static::$primaryKeyTipo)
                ->getSingle();
        
        return static::makeFromRs($rs);
    }
    
    /**
     * 
     * @return AbstractEntity
     */
    public static function primeiro(){
        $q = static::getBasicMoveQuery()->orderby(static::getPkName());
        
        try {
            $rs = $q->getSingle();
            return static::makeFromRs($rs);
        } catch(Exception $ex) {
            error_log("[AbstractEntity->primeiro:" . $q->getQuerySql());
            throw $ex;
        }
    }
    
    /**
     * 
     * @return AbstractEntity
     */
    public static function ultimo(){
        $q = static::getBasicMoveQuery()->orderby(static::getPkName(),"DESC");
        try {
            $rs = $q->getSingle();
            return static::makeFromRs($rs);
        } catch(Exception $ex) {
            error_log("[AbstractEntity->ultimo:" . $q->getQuerySql());
            throw $ex;
        }
    }
    
    /**
     * 
     * @param type $pk
     * @return AbstractEntity
     */
    public static function proximo($pk){
        $q = static::getBasicMoveQuery()
                ->andWhere(static::getPkName())->maiorQue($pk, static::$primaryKeyTipo);
        
        try {
            $rs = $q->getSingle();
            return static::makeFromRs($rs);
        }  catch(Exception $ex) {
            error_log("[AbstractEntity->proximo:" . $q->getQuerySql());
            throw $ex;
        }
    }
    
    /**
     * 
     * @param type $pk
     * @return AbstractEntity
     */
    public static function anterior($pk){
        $q = static::getBasicMoveQuery()
                ->where(static::getPkName())->menorQue($pk, static::$primaryKeyTipo)
                ->orderBy(static::getPkName(),"DESC");
        try {
            $rs = $q->getSingle();
            return static::makeFromRs($rs);
        }  catch(Exception $ex) {
            error_log("[AbstractEntity->anterior:" . $q->getQuerySql());
            throw $ex;
        }
    }
    
    
    /**
     * 
     * @param int $pk
     * @return AbstractEntity
     */
    public static function listar($limit=0, $offset=0){
        $q = static::getBasicMoveQuery();
        
        if($limit) {
            $q->limit($limit);
        }
        
        if($offset) {
            $q->offset($offset);
        }
        
        try {
            return new EntityArray($q->getList(), static::class);
        }  catch(Exception $ex) {
            error_log("[AbstractEntity->listar:" . $q->getQuerySql());
            throw $ex;
        }
    }
    
    /**
     * 
     * @param string $campo
     * @param type $txt
     * @return array
     */
    public static function getProcurarQuery($campo, $txt, $modo="like") {
        $q = static::getBasicMoveQuery();
        
        if(!method_exists($q, $modo)){
            throw new \Exception("Modo de Procura Inválido: $modo");
        }
        
        $q->andWhere(static::getNomeCampo($campo))->$modo($txt, static::getTipoCampo($campo));
        
        return $q;
    }
    
    public static function procurar($campo, $txt, $modo="like") {
        $q = static::getProcurarQuery($campo, $txt, $modo);
        
        try {
            return new EntityArray($q->getList(),static::class);
        } catch(Exception $ex) {
            error_log("[AbstractEntity->procurar:" . $q->getQuerySql());
            throw $ex;
        }
    }
    
    public static function getBy($campo, $txt, $modo="like") {
        $q = static::getProcurarQuery($campo, $txt, $modo);
        
        try {
            return static::makeFromRs($q->getSingle());
        }  catch(Exception $ex) {
            error_log("[AbstractEntity->getBy:" . $q->getQuerySql());
            throw $ex;
        }
    }
    
    public static function listarPor($campo, $txt, $modo="like") {
        $q = static::getProcurarQuery($campo, $txt, $modo);
        
        try {
            return new EntityArray($q->getList(),static::class);
        }  catch(Exception $ex) {
            error_log("[AbstractEntity->listarPor:" . $q->getQuerySql());
            throw $ex;
        }
    }
    
    /**
     * 
     * @param AbstractEntity $obj
     * @return AbstractEntity
     */
    public function insert(){
        $q = LHDB::getConnection()->query(static::$table);
        foreach($this as $key => $val) {
            if(!$val){
                continue;
            } else if($key == "editClone") {
                continue;
            }
            
            $tipo = static::getTipoCampo($key);
            $q->set(static::getNomeCampo($key), $val, $tipo);
        }
        
        try {
            $q->insert();
            $primaryKey  = static::$primaryKey;
            $this->$primaryKey = $q->lastInsertId();
            return $this;
        }  catch(Exception $ex) {
            error_log("[AbstractEntity->insert:" . $q->getInsertSql());
            throw $ex;
        }
    }
    
    /**
     * 
     * @param AbstractEntity $obj
     * @return AbstractEntity
     */
    public function update(){
        $count = 0;
        $q = LHDB::getConnection()->query(static::$table);
        foreach($this as $key => $val) {
            if($key==static::$primaryKey){ // não devo atualizar a chave primaria
                continue;
            } else if($this->editClone && $val == $this->editClone->$key) { // checando se o valor foi alterado
                continue;
            } else if($key == "editClone") {
                continue;
            }
            
            $tipo = static::getTipoCampo($campo);
            
            $q->set(static::getNomeCampo($key), $val, $tipo);
            $count++;
        }
        
        $pkName = static::$primaryKey;
        $q->andWhere(static::getPkName())->equals($this->$pkName, static::$primaryKeyTipo);
        
        if($count>0){
            try {
                $q->update();
            }  catch(Exception $ex) {
                error_log("[AbstractEntity->update:" . $q->getUpdateSql());
                throw $ex;
            }
        }
        
        return $this;
    }
    
    /**
     * 
     * @return AbstractEntity
     */
    public function salvar(){
        $pkName = static::$primaryKey;
        if(property_exists($this, $pkName) && !empty($this->$pkName)){
            return $this->update();
        } else {
            return $this->insert();
        }
    }
    
    /**
     * 
     * @param AbstractEntity $obj
     * @return int
     */
    public function delete(){
        $pkName = static::$primaryKey;
        $q = LHDB::getConnection()->query(static::$table);
        $q->andWhere(static::getPkName())->equals($this->$pkName, static::$primaryKeyTipo);
        
        try {
            return $q->delete();
        }  catch(Exception $ex) {
            error_log("[AbstractEntity->delete:" . $q->getDeleteSql());
            throw $ex;
        }
    }
    
    public function __toString() {
        $pk = static::$primaryKey;
        return static::class . "[" . $this->$pk . "]";
    }
    
    /*
     * @return int
     */
    public function count(){
        $rs = static::getBasicMoveQuery()->campos(array("COUNT(" . static::getPkName() . ") as total"))->getSingle();
        return $rs["total"];
    }

    public function jsonSerialize (){
        $ret = array();
        foreach($this as $key => $val){ 
            $ret[$key] = $val;
        }
        
        $ret["toString"] = "".$this;
        return $ret;
    }
}
