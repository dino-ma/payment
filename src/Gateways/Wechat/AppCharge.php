<?php

/*
 * The file is part of the payment lib.
 *
 * (c) Leo <dayugog@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Payment\Gateways\Wechat;

use Payment\Contracts\IGatewayRequest;
use Payment\Exceptions\GatewayException;
use Payment\Payment;
use Payment\Helpers\ArrayUtil;


/**
 * @package Payment\Gateways\Wechat
 * @author  : Leo
 * @email   : dayugog@gmail.com
 * @date    : 2019/4/1 8:24 PM
 * @version : 1.0.0
 * @desc    : app支付
 **/
class AppCharge extends WechatBaseObject implements IGatewayRequest
{
    const METHOD = 'pay/unifiedorder';

    /**
     * 获取第三方返回结果
     * @param array $requestParams
     * @return mixed
     * @throws GatewayException
     */
    public function request(array $requestParams)
    {
        try {
            $info = $this->requestWXApi(self::METHOD, $requestParams);

            if ($info['return_code'] == 'SUCCESS') {
                $data = [
                    'appid' =>  $info['appid'],
                    'partnerid' =>  $info['mch_id'],
                    'prepayid'  =>  $info['prepay_id'],
                    'package'   =>  'Sign=WXPay',
                    'noncestr'  =>  $info['nonce_str'],
                    'timestamp' =>  time(),
                ];

                $data = ArrayUtil::paraFilter($data);
                $data = ArrayUtil::arraySort($data);

                $signStr        = ArrayUtil::createLinkstring($data);
                $data['sign'] = $this->makeSign($signStr);
                $info = $data;
            }

            return $info;
        } catch (GatewayException $e) {
            throw $e;
        }
    }

    /**
     * @param array $requestParams
     * @return mixed
     */
    protected function getSelfParams(array $requestParams)
    {
        $limitPay = self::$config->get('limit_pay', '');
        if ($limitPay) {
            $limitPay = $limitPay[0];
        } else {
            $limitPay = '';
        }
        $nowTime    = time();
        $timeExpire = intval($requestParams['time_expire']);
        if (!empty($timeExpire)) {
            $timeExpire = date('YmdHis', $timeExpire);
        } else {
            $timeExpire = date('YmdHis', $nowTime + 1800); // 默认半小时过期
        }

        $receipt   = $requestParams['receipt'] ?? false;
        $totalFee  = bcmul($requestParams['amount'], 100, 0);
        $sceneInfo = $requestParams['scene_info'] ?? '';
        if ($sceneInfo) {
            $sceneInfo = json_encode(['store_info' => $sceneInfo]);
        } else {
            $sceneInfo = '';
        }

        $selfParams = [
            'device_info'      => $requestParams['device_info'] ?? '',
            'body'             => $requestParams['subject'] ?? '',
            'detail'           => $requestParams['body'] ?? '',
            'attach'           => $requestParams['return_param'] ?? '',
            'out_trade_no'     => $requestParams['trade_no'] ?? '',
            'fee_type'         => self::$config->get('fee_type', 'CNY'),
            'total_fee'        => $totalFee,
            'spbill_create_ip' => $requestParams['client_ip'] ?? '',
            'time_start'       => date('YmdHis', $nowTime),
            'time_expire'      => $timeExpire,
            'goods_tag'        => $requestParams['goods_tag'] ?? '',
            'notify_url'       => self::$config->get('notify_url', ''),
            'trade_type'       => 'APP',
            'limit_pay'        => $limitPay,
            'receipt'          => $receipt === true ? 'Y' : '',
            'scene_info'       => $requestParams['scene_info'] ?? '', // 场景信息
        ];

        return $selfParams;
    }
}
