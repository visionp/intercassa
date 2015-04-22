<?php
/**
 * Created by PhpStorm.
 * User: VisioN
 * Date: 17.04.2015
 * Time: 16:01
 *
 *
 * Класс предназначен для работы с Интеркассой
 *
 * Intercassa::newPay($amount = null, $user_id = null)
 * -создать новый платеж, передаем сумму, id-пользователя (по умолчанию берется id текущего)
 *
 *
 * Intercassa::updatePay($ip, $postData)
 * - обновить платеж исходя из пришедших данных, в метод
 * передается адресс источника и данные которые были получены
 *
 * Intercassa::getConfigFields()
 * -получить конфигурацию
 *
 */

namespace common\components;

use Yii;
use yii\base\Component;
use common\models\IntercassaPays;
use common\exceptions\ExceptionNullParams;
use common\exceptions\ExceptionsIntercassa;


/**
 * Class Intercassa
 * @package common\components
 */
class Intercassa extends Component {

    const STATUS_SUCCESS = 'success';
    const DEFAULT_CUR    = 'UAH';
    const DEFAULT_STATE    = 'new';

    /**
     * @var
     */
    public $secretTestKey;
    /**
     * @var
     */
    public $secretKey;
    /**
     * @var
     */
    public $IdCassa;
    /**
     * @var null
     */
    public $confFields = null;
    /**
     * @var bool
     */
    public $is_test = true;

    //доверенные ip адресса
    /**
     * @var array
     */
    public $trustedIps = Array(
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
     * @var array
     */
    private $save_fields = Array(
        'ik_inv_st'    => 'invoice_state',     //состояние платежа
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
    );


    /**
     * url отправки данных формы
     * @var string
     */
    private $_actionUrl = 'https://sci.interkassa.com/';


    /**
     * Значение полей по-умолчанию
     * @var array
     */
    private $defaultConf = Array(
        'ik_cur'   => self::DEFAULT_CUR,    //Валюта по умолчанию
        'ik_desc'  => 'Пополнение баланса'  //описнаие платежа
    );


    /**
     * Создает новую запись о платеже со статусом new
     * @param null $amount
     * @param null $user_id
     * @param null $comment
     * @return int|null
     * @throws ExceptionsIntercassa
     */
    public function newPay($amount = null, $user_id = null) {
        return $this->createPay($amount, $user_id);
    }


    /**
     * Обновляет данные о платеже пришедшие с системы Интеркассы
     * перед обновлением проверяются источник, ЭЦП
     * @param $ip
     * @param $postData
     * @return null|object
     */
    public function updatePay($ip, $postData) {

        $return = null;

        if(!isset($postData['ik_pm_no'])) {
            \Yii::error('Id pay is empty', 'intercassa');
            return $return;
        }

        if($this->checkRequest($ip, $postData)){
            $model_pays = IntercassaPays::find()
                ->where(['id'  => $postData['ik_pm_no']])
                ->andWhere(['!=', 'invoice_state', self::STATUS_SUCCESS])
                ->one();

            if(!$model_pays) {
                \Yii::error('Id pay is not exist', 'intercassa');
                return $return;
            }

            foreach($postData as $name => $val) {
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
                \Yii::error($model_pays->getErrors(), 'intercassa');
            }
        }

        return $return;
    }


    /**
     * Возвращает конфигурацию полей
     * @return array
     */
    public function getConfigFields() {
        $_config = Array();

        if(is_array($this->confFields)) {
            $_config = array_merge($_config, $this->confFields);
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
     * @return bool
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
     * @return bool
     */
    private function checkSign(Array $data) {
        $sign = $this->createSign($data);
        $result = $sign === $data['ik_sign'];
        if(!$result) {
            \Yii::error('Secret keys do not match ' . $sign . ' != ' . $data['ik_sign'], 'intercassa');
        }
        return $result;

    }

    /**
     * Проверка IP
     * @param $ip
     * @return bool
     */
    private function checkIps($ip) {
        $result = in_array($ip, $this->trustedIps);
        if(!$result){
            \Yii::error('Unresolved ip ' . $ip, 'intercassa');
        }
        return $result;
    }


    /**
     * Устанавливает значение конф по умолчанию
     * @param $params
     */
    private function setDefaultParams(&$params) {
        foreach($this->defaultConf as $name => $val){
            if(!isset($params[$name])) {
                $params[$name] = $val;
            }
        }
    }


    /**
     * Возвращает id кассы
     * @return mixed
     * @throws ExceptionNullParams
     */
    private function getIdCassa() {
        if(!$this->IdCassa) {
            throw new ExceptionNullParams('Не указан идентификатор кассы');
        }
        return $this->IdCassa;
    }


    /**
     * Создать новую запись о платеже
     * @param null $amount
     * @param null $user_id
     * @param null $comment
     * @return int|null
     * @throws ExceptionsIntercassa
     */
    private function createPay($amount = null, $user_id = null) {
        if(!$user_id) {
            $user_id = \Yii::$app->user->getId();
        }

        $model = new IntercassaPays();

        $model->user_id = $user_id;
        $model->amount     = $amount;
        $model->invoice_state  = self::DEFAULT_STATE;

        if($model->save()) {
            return $model->id;
        } else {
            throw new ExceptionsIntercassa('Не удалось создать запись о счете');
        }
        return null;
    }


    /**
     * Возвращает ЭЦП исходя из переданных данных
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