<?php

namespace SearchableBundle\Service;

use Doctrine\ORM\EntityManager;

/**
 * Description of  SearchableService
 *
 * @author kai
 */
class SearchableService
{

    protected $em;
    protected $entityClass;
    protected $repository;
    protected $trans;
    protected $logger;

    /**
     * search target by filters
     * @param array $filters 指定的搜索条件，
     *              1. 与查询['id'=>'1','name'='abc'] 返回 id=1 and name='abc',
     *              2. 区间['id'=>'>1','name'='abc'] 返回 id>1 and name='abc'， 支持>,<,>=,<=,<>, like.like见下面例子
     *              4. 模糊查询['id'=>'%1%','name'='abc'] 返回 id like %1% and name='abc'，
     *              5. 范围查询['id'=>['>1', '<10'],'name'='abc'] 范围查询,返回 id >1 and id<10 and name='abc'
     *              6. 范围查询['id'=>['>1', '<10'],'name'='abc'] 范围查询,返回 id >1 and id<10 and name='abc'，
     * @param array $sorts 指定返回的顺序，如['id'=>'ASC','name'='DESC','createdBy'='ASC'] , ASC 和 DESC是大小不敏感的
     * @param array $fields 需要返回的字段的名字，如['id','name','createdBy'], 字段对应的是doctrine entity的mapping字段名字而不是数据库的column name
     * @param int $page 查询的页的页码，起始值是1
     * @param int $limit 每页返回的数据条数
     * @param string $distinct 根据什么column来做distinct， 由于doctrine的限制，目前只能针对一个column distinct
     * @return object return by process, see(override) process function for detail
     */
    public function search($filters=[], $sorts=[], $fields=[], $page=1, $limit=10, $distinct=false, $orOpt = false, $qbModifierFn = null){
        $results = $this->repository->search($filters, $sorts, $fields, $page, $limit, $distinct, $orOpt, $qbModifierFn);
        if(array_key_exists('error', $results)){
            return $this->printError($results['error']);
        }else{
            $results['results'] = $this->process($results['results']);
            return $results;
        }
    }
    
    /**
     * Count total base on filter
     * @param array $filters 指定的搜索条件，
     *              1. 与查询['id'=>'1','name'='abc'] 返回 id=1 and name='abc',
     *              2. 区间['id'=>'>1','name'='abc'] 返回 id>1 and name='abc'， 支持>,<,>=,<=,<>, like.like见下面例子
     *              4. 模糊查询['id'=>'%1%','name'='abc'] 返回 id like %1% and name='abc'，
     *              5. 范围查询['id'=>['>1', '<10'],'name'='abc'] 范围查询,返回 id >1 and id<10 and name='abc'
     *              6. 范围查询['id'=>['>1', '<10'],'name'='abc'] 范围查询,返回 id >1 and id<10 and name='abc'，
     * @param array $fields 需要返回的字段的名字，如['id','name','createdBy'], 字段对应的是doctrine entity的mapping字段名字而不是数据库的column name, 这里主要是在使用distince的时候需要
     * @param string $distinct 根据什么column来做distinct， 由于doctrine的限制，目前只能针对一个column distinct
     * @return object return by process, see(override) process function for detail
     */
    public function count($filters=[], $fields=[], $distinct=false){
        $results = $this->repository->count($filters, $fields, $distinct);
        if(array_key_exists('error', $results)){
            return $this->printError($results['error']);
        }else{
            return $results;
        }
    }
    
    protected function process($entities, $simpleMode = false, $fields = null){
        return $entities;
    }
    
    protected function unique($keys){
        $length = 10;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
        }
        if(!empty($keys)){
            sort($keys);
            $keyString = implode('', $keys);
            $randomString = hash('md5', $randomString.$keyString);
            $randomString = substr($randomString, 0, $length);
        }
        return $randomString;
    }
    
    /**
     * 检查并且转换时间
     */
    protected function parseTime($dt){
        if(is_a($dt, 'DateTime')){
            return $dt;
        }else{
            // expect 'Y-m-d H:i:s';
            $dt = new \DateTime($dt);
            $error = \DateTime::getLastErrors()['error_count'];
            if($error == 0){
                return $dt;
            }
            return null;
        }
    }
    
    protected function filterFields($obj, $fs){
        $result = [];
        // var_dump($obj);
        if(is_object($obj)){ // only deal with object with getter

            foreach($fs as $f){
                $name = 'get'.ucfirst($f);
                if(method_exists($obj, $name) && is_callable([$obj, $name])){
                    $val = $obj->{$name}();
                    $result[$f] = $val;
                }else{
                    $result[$f] = null;
                }
            }
        }else if(is_array($obj)){// do not process it if it is array
            $result = $obj;
        }
        return $result;
    }
    
    protected function printError($error=null){
        $this->getLogger()->info('Error from Searchable Service: '.$error);
        return [
            'error' => !empty($error) ? $error : 'Error from Searchable Service'
        ];
    }

    protected function getLogger(){
        return $this->logger;
    }
}
