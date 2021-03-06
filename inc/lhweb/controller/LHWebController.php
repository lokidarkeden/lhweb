<?php
namespace lhweb\controller;

use lhweb\database\GenericQuery;
use lhweb\database\LHDB;
use lhweb\database\LHEntityArray;
use lhweb\database\LHWebEntity;
use lhweb\exceptions\RegistroNaoEncontradoException;

class LHWebController {
    /**
     *
     * @var LHWebEntity
     */
    public $classe_entidade = null;
    
    protected $tabela = null;
    protected $query_listar = null;
    protected $debug = false;
    protected static $debug_joins = false;
    protected static $debug_entity = false;
    protected $max_join_level = 5;
    

    /**
     *
     * @var LHDB
     */
    protected $lhdb = null;
    
    public function __construct($classe_entidade) {
        if(!class_exists($classe_entidade)){
            throw new Exception("LHWebController: Classe Não Encontrada [$classe_entidade]");
        }
        
        $this->classe_entidade = $classe_entidade;
        $this->tabela = static::get_nome_tabela($classe_entidade);
    }
    
    /**
     * 
     * @param string $table
     * @return type
     */
    public function query($table){
        if(!$this->lhdb){
            $this->lhdb = LHDB::getConnection();
        }
        
        return $this->lhdb->query($table);
    }
    
    /**
     * 
     * @param string $classe_entidade
     * @return string
     * Retorna o nome da tabela para a data classe, sendo que esta deve ser 
     * filha de LHWebEntity ( Não Enforçado ).
     */
    public static function get_nome_tabela($classe_entidade){
        if(!class_exists($classe_entidade)){
            error_log("GET NOME TABELA: CLASSE NÃO ENCONTRADA [$classe_entidade]");
            error_log(print_r(debug_backtrace(),true));
            return null;
        } else if($classe_entidade::$tabela) {
            return $classe_entidade::$tabela;
        } else { // Gerando Nomeclatura Padrão da Tabela.
            $class = explode("\\",strtolower($classe_entidade));
            return str_replace("entity", "", strtolower($class[count($class)-1]));
        }
    }
    
    /**
     * 
     * @param string $campo
     * @param boolean $prependNomeTabela
     * @return string
     * Retornar o nome do campo, levando em conta o mapeamento de colunas 
     * para o banco de dados.
     */
    public static function get_nome_campo($classe_entidade, $campo, $tabela=null) {
        $nomecampo = $tabela?$tabela. ".":"";
        if(array_key_exists($campo, $classe_entidade::$mapaCampos)){
            $nomecampo .= $classe_entidade::$mapaCampos[$campo];
        } else {
            $nomecampo .= $campo;
        }
        
        return $nomecampo;
    }
    
    /**
     * 
     * @param type $c
     * @return string
     * Retornar o nome do campo, levando em conta o mapeamento de colunas 
     * para o banco de dados.
     */
    public static function get_tipo_campo($classe_entidade, $c) {
        if(array_key_exists($c, $classe_entidade::$mapaTipos)){
            return $classe_entidade::$mapaTipos[$c];
        } else {
            return LHDB::PARAM_STR;
        }
    }
    
    
    /**
     * 
     * @return string
     * Retorna o nome da coluna chave primaria na tabela.
     */
    public static function get_nome_chave_primaria($classe_entidade, $tabela=null){
        return ($tabela?$tabela.".":"") . $classe_entidade::$nomeChavePrimaria;
    }
    
    /**
     * 
     * @return string
     * Retorna o nome da coluna chave primaria na tabela.
     */
    public static function get_coluna_chave_primaria($classe_entidade, $tabela=null){
        return ($tabela?$tabela.".":"") . static::get_nome_campo($classe_entidade, $classe_entidade::$nomeChavePrimaria);
    }
    
    /**
     * 
     * @return int
     * Retorna o tipo da chave primaria... INT por padrão.
     */
    public static function get_tipo_chave_primaria($classe_entidade){
        return $classe_entidade::$tipoChavePrimaria?$classe_entidade::$tipoChavePrimaria:LHDB::PARAM_INT;
    }
    
    /**
     * 
     * @param type $rs
     * @param string $campo
     */
    public static function get_from_rs($rs, $campo) {
        if(is_array($rs)){
            return array_key_exists($campo, $rs)?$rs[$campo]:null;
        } else if(is_object($rs)){
            return property_exists($campo, $coluna)?$rs->$campo:null;
        }
    }
    
    /**
     * 
     * @param type $rs
     * @return LHWebEntity
     * Recebe um ResultSet com um registro de preenche o objeto Entity.
     * $prefix é o prefixo da tabela no result set
     * join_level é o nivel em que está de recursividade, para evitar loops infititos.
     */
    public static function get_entity_from_rs($classe_entidade, $rs, $prefix="", &$count=1, $join_level=0, $max_join_level=1, $debug_entity=false) {
        $obj = new $classe_entidade();
        
        if($debug_entity) 
            error_log("GET FROM RS [$join_level] [$max_join_level] [$classe_entidade] [$prefix]");
        
        // Checa se a chave primária existe no resultset, caso contrário, retorna null;
        if(static::get_from_rs($rs, static::get_nome_campo($classe_entidade, $classe_entidade::$nomeChavePrimaria))==null){
            return null;
        }
        
        // Percorre os atributos de $obj e preencher do $rs.
        foreach($obj as $key => $val){
            $coluna = $prefix . static::get_nome_campo($classe_entidade, $key);
            $obj->$key = static::get_from_rs($rs, $coluna);
        }
        
        // Incrementando o nível do join, para evitar loops infinitos
        $join_level++;
        
        // Processar Joins se dentro do limite da recursividade
        if($join_level <= $max_join_level) {
            foreach($classe_entidade::$joins as $attr => $join) {
                list($join_class, $join_attr) = $join;
                $join_ctl = $join_class::$controller;
                $obj->$attr = $join_ctl::get_entity_from_rs($join_class, $rs, "j_" . $count++ . "_", $count, $join_level, $max_join_level, $debug_entity);
            }

            foreach($classe_entidade::$leftOuterJoins as $attr => $join) {
                list($join_class, $join_attr) = $join;
                $join_ctl = $join_class::$controller;
                $obj->$attr = $join_ctl::get_entity_from_rs($join_class, $rs, "lj_" . $count++ . "_", $count, $join_level, $max_join_level, $debug_entity);
            }
        } else {
            if($debug_entity) 
                error_log("MAX JOIN EXCEDED [$join_level] [$max_join_level] [$classe_entidade]");
        }
        return $obj;
    }
    
    /**
     * 
     * @param GenericQuery $q
     * @param string $classe_entidade
     * @param string $tabela
     * Cria um objeto da classe entidade, e seta os campos na query.
     * Desconsidera campos agregados por joins.
     */
    public static function set_campos_consulta($q, $classe_entidade, $tabela, $alias=""){
        $obj = new $classe_entidade();
        foreach($obj as $key => $val){
            if(array_key_exists($key, $classe_entidade::$joins) || 
                    array_key_exists($key, $classe_entidade::$leftOuterJoins)) {
                continue;
            }
            
            $nomeCampo = static::get_nome_campo($classe_entidade, $key);
            if($alias){
                $campoAlias = " AS " . $alias . "_" . $nomeCampo;
            } else {
                $campoAlias = "";
            }
            
            $q->addCampo($tabela . "." . $nomeCampo . $campoAlias);
        }
    }
    
    /**
     * 
     * @param string $txt
     */
    public function showDebug($txt) {
        if($this->debug){
            error_log("[".static::class . "] " .$txt);
        }
    }
    
    /**
     * 
     * @return string
     */
    public function getNomeChavePrimaria($prependNomeTabela=false){
        return static::get_nome_chave_primaria($this->classe_entidade, $prependNomeTabela?$this->tabela:null);
    }
    
    /**
     * 
     * @return string
     */
    public function getColunaChavePrimaria($prependNomeTabela=false){
        return static::get_coluna_chave_primaria($this->classe_entidade, $prependNomeTabela?$this->tabela:null);
    }
    
    /**
     * 
     * @return int
     */
    public function getTipoChavePrimaria(){
        $c = $this->classe_entidade;
        return $c::$tipoChavePrimaria;
    }
    
    
    /**
     * 
     * @param GenericQuery $q
     * @param string $classe_entidade
     * @param string $alias_entidade
     * @param string $join_class
     * @param string $join_alias
     * @param string $atributo
     * @param string $campo_join
     * @param string $prefixo_campos
     * @param string $tipo
     * 
     * Executa o Join Entre as Duas classes
     * Os dados da junção vão ser armazenados no $atributo dentro do objeto 
     * principal da classe_entidade.
     * Tempos por padrão o join feito da seguinte maneira:
     * JOIN $join_class AS $join_alias ON $join_alias.PK = $alias_entidade.FK
     * 
     * Caso $atributo contenha um ponto (.), a junção vai ser feita com base em 
     * alguma atributo já unido, procurando nos joins já feitos de $classe_entidade
     * 
     */
    public static function join($q, $classe_entidade, $alias_entidade, $join_class, $join_alias, $atributo, $campo_join, $prefixo_campos, $tipo="join"){
        // Procura o atributo nos joins, para saber qual é classe em questão.
        if(strpos($campo_join, ".")!==false){
            list($atributo_join, $campo_join) = explode(".", $campo_join);
            $count = 0;
            foreach($classe_entidade::$joins as $jattr => $det){
                if($jattr == $atributo_join){
                    return static::$tipo($q, $det[0], static::get_nome_tabela($det[0])."_$count", $join_class, $join_alias, $atributo, $campo_join, $prefixo_campos);
                }
                $count++;
            }
            
            foreach($classe_entidade::$leftOuterJoins as $jattr => $det){
                if($jattr == $atributo_join){
                    return static::$tipo($q, $det[0], static::get_nome_tabela($det[0])."_$count", $join_class, $join_alias, $atributo, $campo_join, $prefixo_campos);
                }
                $count++;
            }
            
            throw new \Exception("[$classe_entidade] " . strtoupper($tipo) . " ON $join_class NÃO ENCONTRADO");
        }
        
        $left_cond  = $join_alias . "." . static::get_coluna_chave_primaria($join_class);
        $right_cond = static::get_nome_campo($classe_entidade, $campo_join, $alias_entidade);
        $joincond = $left_cond . "=" . $right_cond;
        
        $q->$tipo(static::get_nome_tabela($join_class) . " AS " . $join_alias, $joincond);
        static::set_campos_consulta($q, $join_class, $join_alias, $prefixo_campos); // Adiciona os campos da tabela joined na consulta
    }
    
    public static function leftOuterJoin($q, $classe_entidade, $alias_entidade, $join_class, $join_alias, $atributo, $campo_join, $prefixo_campos){
        return static::join($q, $classe_entidade, $alias_entidade, $join_class, $join_alias, $atributo, $campo_join, $prefixo_campos, "leftOuterJoin");
    }
    
    public static function processar_query_joins($q, $classe_entidade, $alias_entidade, $join_count=0, $join_level=0, $max_join_level=1){
        $class_ctl = $classe_entidade::$controller;
        
        $join_level++;
        
        // Processar Joins
        foreach($classe_entidade::$joins as $atributo => $det){
            list($join_class, $campo_join) = $det;
            
            // Se for um subjoin, sempre será leftOuter.
            $join_count++;
            $joinType = $join_level==1?"join":"leftOuterJoin";
            if(static::$debug_joins){
                error_log(str_pad("", $join_level*8,"_"). "ST JOIN [$join_level][$join_count] $join_class");
            }
            
            static::$joinType($q, 
                    $classe_entidade, 
                    $alias_entidade,
                    $join_class, 
                    static::get_nome_tabela($join_class) . "_" . $join_count, 
                    $atributo, 
                    $campo_join, 
                    "j_$join_count"
            );
            
            if($join_level <= $max_join_level){
                $join_ctrl = $join_class::$controller;
                $join_count = static::processar_query_joins($q, $join_class, $join_ctrl::get_nome_tabela($join_class)."_$join_count", $join_count, $join_level, $max_join_level);
            }
        }
        
        // Processar Left Outer Joins
        foreach($classe_entidade::$leftOuterJoins as $atributo => $det){
            list($join_class, $campo_join) = $det;
            
            $join_count++;
            if(static::$debug_joins){
                error_log(str_pad("", $join_level*8,"_") . "LO JOIN [$join_level][$join_count] $join_class");
            }
            static::leftOuterJoin($q, 
                    $classe_entidade,
                    $alias_entidade,
                    $join_class, 
                    static::get_nome_tabela($join_class) . "_" . $join_count, 
                    $atributo, 
                    $campo_join, 
                    "lj_$join_count"
            );
            
            if($join_level <= $max_join_level){
                $join_ctrl = $join_class::$controller;
                $join_count = static::processar_query_joins($q, $join_class, $join_ctrl::get_nome_tabela($join_class)."_$join_count", $join_count, $join_level, $max_join_level);
            }
        }
        
        return $join_count;
    }
    
    /**
     * 
     * @return GenericQuery
     */
    public function getBasicMoveQuery(){
        $classe_entidade = $this->classe_entidade;
        
        // Definindo campos da tabela.
        $q = $this->query($this->tabela)->campos([]);
        static::set_campos_consulta($q, $this->classe_entidade, $this->tabela);
        
        if(static::$debug_joins){
            error_log(str_pad("", 80, "#"));
            error_log("BASIC MOVE QUERY: $classe_entidade");
        }
        static::processar_query_joins($q, $classe_entidade, static::get_nome_tabela($classe_entidade), 0, 0, $this->max_join_level);
        
        if($classe_entidade::$orderBy) {
            $q->orderBy(static::get_nome_campo($classe_entidade, $classe_entidade::$orderBy), $classe_entidade::$orderDirection);
        }
        
        if($classe_entidade::$groupBy) {
            $q->groupBy(static::get_nome_campo($classe_entidade, $classe_entidade::$groupBy));
        }
        
        $this->showDebug("BASIC MOVE QUERY:" . $q->getQuerySql());
        return $q;
    }
    
    public function getListarQuery(){
        if(!$this->query_listar){
            $this->query_listar = $this->getBasicMoveQuery();
        }
        
        return $this->query_listar;
    }
    
    /**
     * 
     * @param string $campo
     * @param boolean $prependNomeTabela
     * @return string
     * Retornar o nome do campo, levando em conta o mapeamento de colunas 
     * para o banco de dados.
     */
    public function getNomeCampo($campo, $prependNomeTabela=false) {
        return static::get_nome_campo($this->classe_entidade, $campo, $prependNomeTabela?$this->tabela:null);
    }
    
    /**
     * 
     * @param type $c
     * @return string
     * Retornar o nome do campo, levando em conta o mapeamento de colunas 
     * para o banco de dados.
     */
    public function getTipoCampo($c) {
        return static::get_tipo_campo($this->classe_entidade, $c);
    }
    
    /**
     * 
     * @param type $rs
     * @return LHWebEntity
     * Recebe um ResultSet com um registro de preenche o objeto Entity.
     */
    public function getEntityFromRS($rs) {
        if(static::$debug_entity){
            error_log("#############################################################################");
            error_log("GET ENTITY FROM RS: " . $this->classe_entidade);
        }
        $count = 1;
        return static::get_entity_from_rs($this->classe_entidade, $rs, "", $count, 0, $this->max_join_level, static::$debug_entity);
    }
    
    /**
     * 
     * @return LHWebEntity
     */
    public function primeiro(){
        $q = $this->getBasicMoveQuery()
                ->orderby($this->getColunaChavePrimaria(true), "ASC");
        return $this->getEntityFromRS($q->getSingle());
    }
    
    /**
     * 
     * @return LHWebEntity
     */
    public function ultimo(){
        $q = $this->getBasicMoveQuery()
                ->orderby($this->getColunaChavePrimaria(true), "DESC");
        return $this->getEntityFromRS($q->getSingle());
    }
    
    /**
     * 
     * @return LHWebEntity
     */
    public function anterior($chave_primaria){
        $q = $this->getBasicMoveQuery()
                ->andWhere($this->getColunaChavePrimaria(true))->menorQue($chave_primaria, $this->getTipoChavePrimaria())
                ->orderby($this->getColunaChavePrimaria(true), "DESC");
        return $this->getEntityFromRS($q->getSingle());
    }
    
    /**
     * 
     * @return LHWebEntity
     */
    public function proximo($chave_primaria){
        $q = $this->getBasicMoveQuery()
                ->andWhere($this->getColunaChavePrimaria(true))->maiorQue($chave_primaria, $this->getTipoChavePrimaria())
                ->orderby($this->getColunaChavePrimaria(true), "ASC");
        return $this->getEntityFromRS($q->getSingle());
    }
    
    /**
     * 
     * @return LHWebEntity
     */
    public function getByPK($chave_primaria){
        $q = $this->getBasicMoveQuery()
                ->andWhere($this->getColunaChavePrimaria(true))->equals($chave_primaria, $this->getTipoChavePrimaria());
        return $this->getEntityFromRS($q->getSingle());
    }
    
    /**
     * 
     * @return int
     * @throws RegistroNaoEncontradoException
     */
    public function apagar($chave_primaria){
        $obj = $this->getByPK($chave_primaria);
        
        if($obj){
            $this->preApagar($obj);
            $q = $this->getBasicMoveQuery()
                    ->andWhere($this->getNomeChavePrimaria(true))->equals($chave_primaria, $this->getTipoChavePrimaria());
            $this->showDebug("DELETE SQL: " . $q->getDeleteSql());
            $q->delete();
            $this->posApagar($obj);
            return $this->anterior($chave_primaria);
        } else {
            throw new RegistroNaoEncontradoException("PK:".htmlspecialchars($chave_primaria));
        }
    }
    
    /**
     * 
     * @param LHWebEntity $obj
     */
    public function validar($obj){
        if($obj == null){
            throw new RegistroNaoEncontrado();
        }
    }
    
    
    /**
     * 
     * @param LHWebEntity $obj
     * @return LHWebEntity
     */
    public function salvar($obj){
        $this->validar($obj);
        $this->preSalvar($obj);

        $pkName = $this->getNomeChavePrimaria();
        
        /**
         * Caso a chave primaria esteja definida, chama o metodo update e seus
         * respectivos eventos, do contrario, o metodo insert.
         */
        if(property_exists($obj, $pkName) && !empty($obj->$pkName)){
            $chave_primaria = $obj->$pkName;
            $obj1 = $this->getByPK($chave_primaria);
            
            $this->preUpdate($obj);
            $this->update($obj);
            
            $obj2 = $this->getByPK($chave_primaria); // Obtem uma cópia atualizada do objetdo no banco de dados.
            $this->posUpdate($obj1, $obj2);
        } else {
            $this->preInsert($obj);
            $chave_primaria = $this->insert($obj);
            
            $obj2 = $this->getByPK($chave_primaria);
            $this->posInsert($obj2);
        }
        $this->posSalvar($obj2);
            
        return $obj2;
    }
    
    /**
     * 
     * @param LHWebEntity $obj
     * @return int
     * Monta a SQL e Persiste o novo Objeto  no Banco
     */
    protected function insert($obj) {
        $classe_entidade = $this->classe_entidade;
        $q = $this->query($this->tabela);
        
        $this->showDebug("====== INSERT ======");
        foreach($obj as $key => $val) {
            if(!isset($val)
                || array_key_exists($key, $classe_entidade::$joins)
                || array_key_exists($key, $classe_entidade::$leftOuterJoins)
                || in_array($key, $classe_entidade::$camposNaoInserir) 
                || in_array($key, $classe_entidade::$camposSomenteLeitura)
            ){
                $this->showDebug("== SKIP: [CAMPO:$key] [VAL: $val]");
                continue;
            }
            
            $this->showDebug("== SET: [CAMPO:$key] [VAL: $val]");
            $q->set($this->getNomeCampo($key), $val, $this->getTipoCampo($key));
        }
        
        $this->showDebug("INSERT SQL:" . $q->getInsertSql());
        $this->showDebug("INSERT VALORES:" . print_r($q->getValoresInsertUpdate(),true));
        
        $q->insert();
        return $q->lastInsertId();
    }
    
    /**
     * 
     * @param LHWebEntity $obj
     * @return LHWebEntity
     */
    protected function update($obj) {
        $classe_entidade = $this->classe_entidade;
        $q = $this->query($this->tabela);
        
        $nome_chave_primaria = $this->getNomeChavePrimaria();
        
        $this->showDebug("====== UPDATE ======");
        foreach($obj as $key => $val) {
            if(!isset($val)
                    || $key == $nome_chave_primaria
                    || array_key_exists($key, $classe_entidade::$joins) 
                    || array_key_exists($key, $classe_entidade::$leftOuterJoins) 
                    || in_array($key, $classe_entidade::$camposNaoAlterar) 
                    || in_array($key, $classe_entidade::$camposSomenteLeitura)
            ){
                $this->showDebug("== SKIP: [CAMPO:$key] [VAL: $val]");
                continue;
            }
            
            $this->showDebug("== SET: [CAMPO:$key] [VAL: $val]");
            $q->set($this->getNomeCampo($key), $val, $this->getTipoCampo($key));
        }
        
        $q->where($nome_chave_primaria)->equals($obj->$nome_chave_primaria);
        
        $this->showDebug("UPDATE SQL:" . $q->getUpdateSql());
        $this->showDebug("UPDATE VALORES:" . print_r($q->getValoresInsertUpdate(),true));
        
        return $q->update();
    }
    
    /**
     * 
     * @param LHWebEntity $obj
     * @return LHWebEntity
     */
    public function preUpdate($obj){}
    
    /**
     * 
     * @param LHWebEntity $old
     * @param LHWebEntity $new
     */
    public function posUpdate($old, $new){}
    
    /**
     * 
     * @param LHWebEntity $obj
     * @return LHWebEntity
     */
    public function posInsert($new){}
    
    /**
     * 
     * @param LHWebEntity $obj
     * @return LHWebEntity
     */
    public function preInsert($obj){}
    
    /**
     * 
     * @param LHWebEntity $obj
     * @return LHWebEntity
     */
    public function preSalvar($obj){}
    
    /**
     * 
     * @param LHWebEntity $obj
     * @return LHWebEntity
     */
    public function posSalvar($obj){}
    
    
    /**
     * 
     * @param LHWebEntity $obj
     */
    public function preApagar($obj){}
    
    /**
     * 
     * @param LHWebEntity $obj
     */
    public function posApagar($obj){}
    
    public function listar($limit=0, $offset=0){
        $q = $this->query_listar?$this->query_listar:$this->getBasicMoveQuery();
        
        if($limit) {
            $q->limit($limit);
        }
        
        if($offset) {
            $q->offset($offset);
        }
        
        try {
            $this->query_listar = null;
            return new LHEntityArray($q->getList(), $this);
        }  catch(Exception $ex) {
            throw $ex;
        }
    }
    
    public static function get_join_count($classe_entitdade, $join_level, $max_join_level){
        $count = 0;
        $join_level++;
        foreach($classe_entitdade::$joins as $attributo => $det) {
            list($classe_join, $foreign_key) = $det;
            $count++;
            
            if($join_level <= $max_join_level){
                $count += static::get_join_count($classe_join, $join_level, $max_join_level);
            }
        }
        
        foreach($classe_entitdade::$leftOuterJoins as $attributo => $det) {
            list($classe_join, $foreign_key) = $det;
            $count++;
            
            if($join_level <= $max_join_level){
                $count += static::get_join_count($classe_join, $join_level, $max_join_level);
            }
        }
        
        return $count;
    }
    
    
    /**
     * 
     * @param string $campo
     * @return string
     * Retorna o nome do campo a ser utilizado nas consultas de procura, levando 
     * em conta campos de classes associadas ( Joinned Tables ), 
     */
    public static function getNomeCampoProcura($classe_entitdade, $campo, $max_join_level=5, &$joinCount=1) {
        if(strpos($campo, ".")!==false){ // PROCURAR NOS JOINS
            list($atributo_join, $campo) = explode(".", $campo);
            
            foreach($classe_entitdade::$joins as $attributo => $det) {
                list($classe_join, $foreign_key) = $det;
                if($atributo_join==$attributo){
                    return static::get_nome_campo($classe_join, $campo, static::get_nome_tabela($classe_join) . "_$joinCount");
                }
                $joinCount++;
                
                // Incrementando os Joins da Classe na contagem
                $joinCount += static::get_join_count($classe_join, 1, $max_join_level);
            }
            
            foreach($classe_entitdade::$leftOuterJoins as $attributo => $det) {
                list($classe_join, $foreign_key) = $det;
                if($atributo_join==$attributo){
                    return static::get_nome_campo($classe_join, $campo, static::get_nome_tabela($classe_join) . "_$joinCount");
                }
                $joinCount++;
                
                // Incrementando os Joins da Classe na contagem
                $joinCount+= static::get_join_count($classe_join, 1, $max_join_level);
            }
        } else {
            // Retorna o nome do campo precedido da tabela.
            return static::get_nome_campo($classe_entitdade, $campo, static::get_nome_tabela($classe_entitdade)); 
        }
    }
    
    function getQueryProcurar(){
        return $this->getBasicMoveQuery();
    }
    
    function getQueryProcurarCampoString($campo, $valor, $modo="like"){
        $classe_entidade = $this->classe_entidade;
        $q = $this->getQueryProcurar();
        $q->andWhere(static::getNomeCampoProcura($classe_entidade, $campo, $this->max_join_level))->$modo($valor, $this->getTipoCampo($campo));
        return $q;
    }
    
    function getQueryProcurarCampoArray($campos, $valor, $modo="like"){
        $classe_entidade = $this->classe_entidade;
        $q = $this->getQueryProcurar();
        
        $q->andWhere("(");
        foreach($campos as $key => $campo){
            $this->showDebug("CAMPO PROCURAR: $campo");
            $q->orWhere(static::getNomeCampoProcura($classe_entidade, $campo, $this->max_join_level))->$modo(is_array($valor)?$valor[$key]:$valor, $this->getTipoCampo($campo));
        }
        $q->Where(")");
        
        return $q;
    }
    
    
    /**
     * @param string $campo
     * @param string $valor
     * @return LHWebEntity
     */
    public function procurar($campo, $valor, $limit=0, $offset=0){
        $obj = $this->classe_entidade;
        if(is_array($campo)){
            $q = $this->getQueryProcurarCampoArray($campo, $valor);
        } else {
            $q = $this->getQueryProcurarCampoString($campo, $valor);
        }
        
        if($limit) { $q->limit($limit); }
        if($offset) { $q->offset($offset); }
        
        $this->showDebug("== PROCURAR QUERY  : " . $q->getQuerySql());
        return new LHEntityArray($q->getList(), $this);
    }
    
    public function getBy($campo, $txt, $modo="like") {
        $q = $this->getBasicMoveQuery();
        $q->andWhere($this->getNomeCampo($campo))->$modo($txt);
        return $this->getEntityFromRS($q->getSingle());
    }
    
    public function listarPor($campo, $txt, $modo="equals") {
        $q = $this->getListarQuery();
        
        if(!is_array($txt)){
            $txt = [$txt];
        }
        
        if(is_array($campo)){
            foreach($campo as $key => $c){
                $q->andWhere($this->getNomeCampo($c,true))->$modo($txt[$key]);
            }
        } else {
            if($modo=="in"){
                $q->andWhere($this->getNomeCampo($campo,true))->in($txt);
            } else {
                foreach($txt as $key => $val){
                    $q->andWhere($this->getNomeCampo($campo,true))->$modo($val);
                }
            }
        }
        
        $this->showDebug("-----------------------------------------------------------------------------------");
        $this->showDebug("LISTAR POR QUERY: " . $q->getQuerySql());
        return new LHEntityArray($q->getList(), $this);
    }
    
    /*
     * @return int
     */
    public function count(){
        $rs = $this->getBasicMoveQuery()
                ->campos(array("COUNT(" . $this->getColunaChavePrimaria(true) . ") as total"))
                ->getSingle();
        return $rs["total"];
    }
    
    /**
     * @param string $campo
     * @param string $valor
     * @return LHWebEntity
     */
    public function procurarCount($campo, $valor){
        $obj = $this->classe_entidade;
        if(is_array($campo)){
            $q = $this->getQueryProcurarCampoArray($campo, $valor);
        } else {
            $q = $this->getQueryProcurarCampoString($campo, $valor);
        }
        
        $q->campos(array("COUNT(" . $this->getColunaChavePrimaria(true). ") as total"));
        
        $this->showDebug("== PROCURAR COUNT QUERY: " . $q->getQuerySql());
        
        $rs = $q->getSingle();
        return $rs["total"];
    }
    
}
