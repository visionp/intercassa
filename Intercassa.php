<?php
/**
 * Created by PhpStorm.
 * User: VisioN
 * Date: 17.04.2015
 * Time: 16:01
 *
 * Компонент Intercassa
 * предоставляет api
 * для работі с системой
 *
 */

namespace common\components;

use Yii;
use yii\base\Component;
use vision\interkassa\models\IntercassaPays;
use vision\interkassa\exceptions\IntercassaException;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\base\UnknownClassException;


/**
 * Class Intercassa
 * @package common\components
 */
class Intercassa extends Component {

    const STATUS_SUCCESS = 'success';
    const DEFAULT_CUR    = 'UAH';
    const DEFAULT_STATE  = 'new';


    public $successPay;
    public $secret_test_key;
    public $secret_key;
    public $id_cassa;
    public $config = [];
    public $is_test = true;


    /**
     * Доверенные ip адресса
     * @var array
     */
    public $trusted_ips = Array(
        '151.80.190.97',
        '151.80.190.98',
        '151.80.190.99',
        '151.80.190.100',
        '151.80.190.101',
        '151.80.190.102',
        '151.80.190.103',
        '151.80.190.104'
    );


    /**
     * Поля платежа которые мы храним и обновляем
     *
     * @var array
     */
    protected $save_fields = [
        'ik_inv_st'    => 'invoice_state',     //Состояние платежа
        'ik_inv_id'    => 'invoice_id',        //Идентификатор платежа
        'ik_trn_id'    => 'transaction_id',    //Идентификатор транзакции
        'ik_co_prs_id' => 'checkout_purse_id', //Идентификатор кошелька кассы
        'ik_cur'       => 'currency',          //Валюта платежа
        'ik_am'        => 'amount',            //Сумма платежа
        'ik_ps_price'  => 'paysystem_price',   //Сумма платежа в платежной системе
        'ik_co_rfn'    => 'checkout_refund',   //Сумма зачисления на счет кассы
        'ik_desc'      => 'description',       //Описание платежа
        'ik_pw_via'    => 'payway_via',        //Выбранный способ оплаты
        'ik_inv_crt'   => 'invoice_created',   //Время создания платежа
        'ik_inv_prc'   => 'invoice_processed'  //Время проведения
    ];


    /**
     * url отправки данных формы
     */
    protected $_actionUrl = 'https://sci.interkassa.com/';


    /**
     * Значение полей по-умолчанию
     *
     * @var array
     */
    protected $default = [
        'ik_cur'   => self::DEFAULT_CUR,    //Валюта по умолчанию
        'ik_desc'  => 'Пополнение баланса'  //описание платежа
    ];


    /**
     * Создаем новую запись о платеже со статусом new
     *
     * @param null $amount
     * @param null $user_id
     * @return int|null
     * @throws IntercassaException
     */
    public function newPay($amount = null, $user_id = null) {
        return $this->createPay($amount, $user_id);
    }


    /**
     * Обновляет данные о платеже пришедшие с Интеркассы
     * перед обновлением проверяются источник, ЭЦП
     *
     * @param $ip
     * @param $postData
     *
     * @throws IntercassaException
     *
     * @return IntercassaPays
     */
    public function updatePay($ip, $data) {
        $return = null;

        if(!isset($data['ik_pm_no'])) {
            throw new ExceptionsIntercassa('Id pay is empty', $postData);
        }

        if($this->checkRequest($ip, $data)){
            $model_pays = IntercassaPays::find()
                ->where(['id'  => $data['ik_pm_no']])
                ->andWhere(['!=', 'invoice_state', self::STATUS_SUCCESS])
                ->one();

            if(!$model_pays) {
                throw new IntercassaException('Id pay is not exist', $data);
                return $return;
            }

            foreach($data as $name => $val) {
                if(array_key_exists($name, $this->save_fields) && $val) {
                    $field_name = $this->save_fields[$name];
                    if($model_pays->hasAttribute($field_name)) {
                        $model_pays->$field_name = $val;
                    }
                }
            }

            if($model_pays->save()) {
                return $model_pays;
            } else {
                throw new IntercassaException(implode(', ',$model_pays->getErrors()));
            }
        }

        return $return;
    }


    /**
     * Возвращает конфигурацию
     * @return array
     */
    public function getConfigFields() {
        $_config = [];

        if(is_array($this->conf_ields)) {
            $_config = array_merge($_config, $this->conf_fields);
        }

        $_config['ik_co_id'] = $this->getIdCassa();
        $this->setDefaultParams($_config);
        return $_config;
    }


    /**
     * Возвращает ссылку формы
     * @return string
     */
    public function getActionUrl() {
        return $this->_actionUrl;
    }


    /**
     * Проверка источника пришедших данных
     * @param $ip
     * @param $postData
     *
     * @return boolean
     */
    private function checkRequest($ip, $postData) {
        $result = false;
        if($this->checkIps($ip)) {
            $result = $this->checkSign($postData);
        }
        return $result;
    }


    /**
     * Проверка ЭЦП
     * @param array $data
     *
     * @throws IntercassaException
     *
     * @return boolean
     */
    private function checkSign(Array $data) {
        $sign = $this->createSign($data);
        $result = $sign === $data['ik_sign'];
        if(!$result) {
            throw new IntercassaException('Secret keys do not match ' . $sign . ' != ' . $data['ik_sign']);
        }
        return $result;

    }


    /**
     * Проверка IP
     *
     * @param $ip
     * @return bool
     * @throws IntercassaException
     */
    protected function checkIps($ip) {
        $result = in_array($ip, $this->trustedIps);
        if(!$result){
            throw new IntercassaException('Unresolved ip ' . $ip);
        }
        return $result;
    }


    /**
     * Устанавливает значение конф по умолчанию
     * @param $params
     */
    protected function setDefaultParams(&$params) {
        foreach($this->default_conf as $name => $val){
            if(!isset($params[$name])) {
                $params[$name] = $val;
            }
        }
    }


    /**
     * Возвращает id кассы
     *
     * @return mixed
     * @throws InvalidConfigException
     */
    protected function getIdCassa() {
        if(!$this->id_cassa) {
            throw new InvalidConfigException('Не указан идентификатор кассы.');
        }
        return $this->id_cassa;
    }


    /**
     * Создать новую запись о платеже
     *
     * @param null $amount
     * @param null $user_id
     * @return array|bool
     * @throws IntercassaException
     */
    protected function createPay($amount = null, $user_id = null) {
        if(!$user_id) {
            $user_id = \Yii::$app->user->getId();
        }

        $model = new IntercassaPays([
            'user_id' => $user_id,
            'amount'  => $amount,
            'invoice_state' => self::DEFAULT_STATE
        ]);

        if($model->save()) {
            return $model->toArray();
        } else {
            throw new IntercassaException('Не удалось создать запись о счете.');
        }
        return false;
    }


    /**
     * Возвращает ЭЦП исходя из переданных данных
     *
     * @param array $dataSet
     * @return string
     */
    private function createSign (Array $dataSet) {
        $key = $this->is_test ? $this->secretTestKey : $this->secretKey;
        //убираем все ключи имя которых начинается не с ik_
        foreach($dataSet as $name => $val) {
            if(!preg_match('/^ik_.*/', $name)) {
                unset($dataSet[$name]);
            }
        }
        unset($dataSet['ik_sign']); //удаляем из данных строку подписи
        ksort($dataSet, SORT_STRING); // сортируем по ключам в алфавитном порядке элементы массива
        array_push($dataSet, $key); // добавляем в конец массива "секретный ключ"
        $signString = implode(':', $dataSet); // конкатенируем значения через символ ":"
        $sign = base64_encode(md5($signString, true)); // берем MD5 хэш в бинарном виде по сформированной строке и кодируем в BASE64
        return $sign;
    }

} 