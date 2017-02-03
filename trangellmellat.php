<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_Guru
 * @subpackage 	trangell_Mellat
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 20016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
if (!class_exists ('checkHack')) {
	require_once( JPATH_PLUGINS . '/k2store/payment_trangellmellat/trangell_inputcheck.php');
}

jimport('joomla.application.menu');
jimport( 'joomla.html.parameter' );

class plgGurupaymentTrangellMellat extends JPlugin{

	var $_db = null;
    
	function plgGurupaymentTrangellMellat(&$subject, $config){
		$this->_db = JFactory :: getDBO();
		parent :: __construct($subject, $config);
	}
	
	function onReceivePayment(&$post){
		if($post['processor'] != 'trangellmellat'){
			return 0;
		}	
		
		$params = new JRegistry($post['params']);
		$default = $this->params;
        
		$out['sid'] = $post['sid'];
		$out['order_id'] = $post['order_id'];
		$out['processor'] = $post['processor'];
		$Amount = round($this->getPayerPrice($out['order_id']),0);

		if(isset($post['txn_id'])){
			$out['processor_id'] = JRequest::getVar('tx', $post['txn_id']);
		}
		else{
			$out['processor_id'] = "";
		}
		if(isset($post['custom'])){
			$out['customer_id'] = JRequest::getInt('cm', $post['custom']);
		}
		else{
			$out['customer_id'] = "";
		}
		if(isset($post['mc_gross'])){
			$out['price'] = JRequest::getVar('amount', JRequest::getVar('mc_amount3', JRequest::getVar('mc_amount1', $post['mc_gross'])));
		}
		else{
			$out['price'] = $Amount;
		}
		$out['pay'] = $post['pay'];
		if(isset($post['email'])){
			$out['email'] = $post['email'];
		}
		else{
			$out['email'] = "";
		}
		$out["Itemid"] = $post["Itemid"];

		$cancel_return = JURI::root().'index.php?option=com_guru&controller=guruBuy&processor='.$param['processor'].'&task='.$param['task'].'&sid='.$param['sid'].'&order_id='.$post['order_id'].'&pay=fail';		
		//=====================================================================

		$app	= JFactory::getApplication();
		$jinput = $app->input;
		$ResCode = $jinput->post->get('ResCode', '1', 'INT'); 
		$SaleOrderId = $jinput->post->get('SaleOrderId', '1', 'INT'); 
		$SaleReferenceId = $jinput->post->get('SaleReferenceId', '1', 'INT'); 
		$RefId = $jinput->post->get('RefId', 'empty', 'STRING'); 
		if (checkHack::strip($RefId) != $RefId )
			$RefId = "illegal";
		$CardNumber = $jinput->post->get('CardHolderPan', 'empty', 'STRING'); 
		if (checkHack::strip($CardNumber) != $CardNumber )
			$CardNumber = "illegal";
	
		if (
			checkHack::checkNum($ResCode) &&
			checkHack::checkNum($SaleOrderId) &&
			checkHack::checkNum($SaleReferenceId) 
		  ){
			if ($ResCode != '0') {
				$out['pay'] = 'fail';
				$msg= $this->getGateMsg($ResCode); 
				$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error');
			}
			else {
				$fields = array(
				'terminalId' => $params->get('melatterminalId'),
				'userName' => $params->get('melatuser'),
				'userPassword' => $params->get('melatpass'),
				'orderId' => $SaleOrderId, 
				'saleOrderId' =>  $SaleOrderId, 
				'saleReferenceId' => $SaleReferenceId
				);
				try {
					$soap = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
					$response = $soap->bpVerifyRequest($fields);

					if ($response->return != '0') {
						$out['pay'] = 'fail';
						$msg= $this->getGateMsg($response->return); 
						$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error');
					}
					else {	
						$response = $soap->bpSettleRequest($fields);
						if ($response->return == '0' || $response->return == '45') {
							$out['pay'] = 'ipn';
							$message = "کد پیگیری".$SaleReferenceId;
							$app->enqueueMessage($message, 'message');
						}
						else {
							$out['pay'] = 'fail';
							$msg= $this->getGateMsg($response->return); 
							$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error');
						}
					}
				}
				catch(\SoapFault $e)  {
					$out['pay'] = 'fail';
					$msg= $this->getGateMsg('error'); 
					$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error');
				}
			}
		}
		else {
			$out['pay'] = 'fail';
			$msg= $this->getGateMsg('hck2'); 
			$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
		}

		return $out;
	}

	function onSendPayment(&$post){
		if($post['processor'] != 'trangellmellat'){
			return false;
		}

		$params = new JRegistry($post['params']);
		$param['option'] = $post['option'];
		$param['controller'] = $post['controller'];
		$param['task'] = $post['task'];
		$param['processor'] = $post['processor'];
		$param['order_id'] = @$post['order_id'];
		$param['sid'] = @$post['sid'];
		$param['Itemid'] = isset($post['Itemid']) ? $post['Itemid'] : '0';
		foreach ($post['products'] as $i => $item){ $price += $item['value']; }  
		$cancel_return = JURI::root().'index.php?option=com_guru&controller=guruBuy&processor='.$param['processor'].'&task='.$param['task'].'&sid='.$param['sid'].'&order_id='.$post['order_id'].'&pay=fail';


		$app	= JFactory::getApplication();
		$dateTime = JFactory::getDate();
			
		$fields = array(
			'terminalId' => $params->get('melatterminalId'),
			'userName' => $params->get('melatuser'),
			'userPassword' => $params->get('melatpass'),
			'orderId' => time(),
			'amount' => round($price,0),
			'localDate' => $dateTime->format('Ymd'),
			'localTime' => $dateTime->format('His'),
			'additionalData' => '',
			'callBackUrl' => JURI::root().'index.php?option=com_guru&controller=guruBuy&processor='.$param['processor'].'&task='.$param['task'].'&sid='.$param['sid'].'&order_id='.$post['order_id'].'&customer_id='.intval($post['customer_id']).'&pay=wait',
			'payerId' => 0,
		);
		
		try {
			$soap = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
			$response = $soap->bpPayRequest($fields);
			
			$response = explode(',', $response->return);
			if ($response[0] != '0') { // if transaction fail
				$msg = $this->getGateMsg($response[0]); 
				$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
			}
			else { // if success
				$refId = $response[1];
				return '
						<form id="paymentForm" method="post" action="https://bpm.shaparak.ir/pgwchannel/startpay.mellat">
							<input type="hidden" name="RefId" value="'.$refId.'" />
						</form>
						<script type="text/javascript">
						document.getElementById("paymentForm").submit();
						</script>'
					;
			}
		}
		catch(\SoapFault $e) {
			$msg= getGateMsg('error');
			$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
		}
	}
	
	function getGateMsg ($msgId) {
		switch($msgId){
			case '0': $out =  'تراکنش با موفقیت انجام شد'; break;
			case '11': $out =  'شماره کارت نامعتبر است'; break;
			case '12': $out =  'موجودی کافی نیست'; break;
			case '13': $out =  'رمز نادرست است'; break;
			case '14': $out =  'تعداد دفعات وارد کردن رمز بیش از حد مجاز است'; break;
			case '15': $out =  'کارت نامعتبر است'; break;
			case '16': $out =  'دفعات برداشت وجه بیش از حد مجاز است'; break;
			case '17': $out =  'کاربر از انجام تراکنش منصرف شده است'; break;
			case '18': $out =  'تاریخ انقضای کارت گذشته است'; break;
			case '19': $out =  'مبلغ برداشت وجه بیش از حد مجاز است'; break;
			case '21': $out =  'پذیرنده نامعتبر است'; break;
			case '23': $out =  'خطای امنیتی رخ داده است'; break;
			case '24': $out =  'اطلاعات کاربری پذیرنده نادرست است'; break;
			case '25': $out =  'مبلغ نامتعبر است'; break;
			case '31': $out =  'پاسخ نامتعبر است'; break;
			case '32': $out =  'فرمت اطلاعات وارد شده صحیح نمی باشد'; break;
			case '33': $out =  'حساب نامعتبر است'; break;
			case '34': $out =  'خطای سیستمی'; break;
			case '35': $out =  'تاریخ نامعتبر است'; break;
			case '41': $out =  'شماره درخواست تکراری است'; break;
			case '42': $out =  'تراکنش Sale‌ یافت نشد'; break;
			case '43': $out =  'قبلا درخواست Verify‌ داده شده است'; break;
			case '44': $out =  'درخواست Verify‌ یافت نشد'; break;
			case '45': $out =  'تراکنش Settle‌ شده است'; break;
			case '46': $out =  'تراکنش Settle‌ نشده است'; break;
			case '47': $out =  'تراکنش  Settle یافت نشد'; break;
			case '48': $out =  'تراکنش Reverse شده است'; break;
			case '49': $out =  'تراکنش Refund یافت نشد'; break;
			case '51': $out =  'تراکنش تکراری است'; break;
			case '54': $out =  'تراکنش مرجع موجود نیست'; break;
			case '55': $out =  'تراکنش نامعتبر است'; break;
			case '61': $out =  'خطا در واریز'; break;
			case '111': $out =  'صادر کننده کارت نامعتبر است'; break;
			case '112': $out =  'خطا سوییج صادر کننده کارت'; break;
			case '113': $out =  'پاسخی از صادر کننده کارت دریافت نشد'; break;
			case '114': $out =  'دارنده کارت مجاز به انجام این تراکنش نیست'; break;
			case '412': $out =  'شناسه قبض نادرست است'; break;
			case '413': $out =  'شناسه پرداخت نادرست است'; break;
			case '414': $out =  'سازمان صادر کننده قبض نادرست است'; break;
			case '415': $out =  'زمان جلسه کاری به پایان رسیده است'; break;
			case '416': $out =  'خطا در ثبت اطلاعات'; break;
			case '417': $out =  'شناسه پرداخت کننده نامعتبر است'; break;
			case '418': $out =  'اشکال در تعریف اطلاعات مشتری'; break;
			case '419': $out =  'تعداد دفعات ورود اطلاعات از حد مجاز گذشته است'; break;
			case '421': $out =  'IP‌ نامعتبر است';  break;
			case 'error': $out ='خطا غیر منتظره رخ داده است';break;
			case 'hck2': $out = 'لطفا از کاراکترهای مجاز استفاده کنید';break;
			case 'notff': $out = 'سفارش پیدا نشد';break;
			case 'price': $out = 'مبلغ وارد شده کمتر از ۱۰۰۰ ریال می باشد';break;
			default: $out ='خطا غیر منتظره رخ داده است';break;
		}
		return $out;
	}


	function getPayerPrice ($id) {
		$user = JFactory::getUser();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('amount')
			->from($db->qn('#__guru_order'));
		$query->where(
			$db->qn('userid') . ' = ' . $db->q($user->id) 
							. ' AND ' . 
			$db->qn('id') . ' = ' . $db->q($id)
		);
		$db->setQuery((string)$query); 
		$result = $db->loadResult();
		return $result;
	}
	
}

?>
