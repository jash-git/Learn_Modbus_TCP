<?php
//05.pdf-Chapter 6 Planning Your Application Program
define('READ_HOLDING_REGISTERS', 0x03);//05.pdf-6.3.2 Modbus Function Code Introductions(P140)
define('READ_INPUT_REGISTERS', 0x04);//05.pdf-6.3.2 Modbus Function Code Introductions(P140)
define('MBAP_HEADER_SIZE', 7); //7bytes
define('MODBUS_TCP_MAX_ADU_SIZE', 260); //260bytes
define('MODBUS_TCP_MIN_RESPONSE_DATA_SIZE', 2); //2bytes
define('MODBUS_TCP_UNPACK_FUNC_BYTECOUNT', 'Cfunc/Cbyte_count');

//check MODBUS TCP MBAP header
function check_mbap_header(&$header)
{
    assert(is_array($header));
    
    if($header['pid'] !== 0)
    {
        return false;
    }
    
    if($header['len'] < MODBUS_TCP_MIN_RESPONSE_DATA_SIZE || $header['len'] > (MODBUS_TCP_MAX_ADU_SIZE - MBAP_HEADER_SIZE + 1)) 
    {       
        return false;
    }        
    
    return true;
}


//check response from 
function modbus_tcp_check_read_reg_response_pdu(&$pdu, $func, &$exception, $byte_count)
{
    assert(is_string($pdu));
    assert(is_int($func));
    assert(isset($exception)); 
    assert(is_int($byte_count));
    
    //check function code and byte count
    if( ord($pdu{0}) == $func and $byte_count == ord($pdu{1}))
    {
        return true;
    }        
    
    $exception = 0;
    if(ord($pdu{0}) === ($func | 0x80))
    {    
        $exception = ord($pdu{1});
    }
    
    return false;
}


//generate MODBUS TCP read holding register(s) or input register(s) command
function modbus_tcp_build_read_reg_cmd(&$tid, $uid, $func, $address, $quantity)
{
    assert(is_int($tid));
    assert(is_int($uid));
    assert($func === READ_HOLDING_REGISTERS || $func === READ_INPUT_REGISTERS);
    assert(is_int($func));
    assert(is_int($quantity));
    $pdu = pack('Cnn', $func, $address, $quantity);
    $header = pack('nxxnC', ++$tid, strlen($pdu) + 1, $uid);
    return ($header . $pdu);
}


//receive TCP packet until done it
function tcp_recv_until_done($fp, &$buffer, $length)
{
    assert(is_resource($fp)); 
    assert(is_int($length));
    $count = 0;
    while($length > $count)
    {
        $tmp = fread($fp, $length - $count);
        if(!$tmp)
        {
            return false;
        }            
        $count += strlen($tmp);
        $buffer .= $tmp;
    }
    
    return true;
}


//convert MBAP header to array
function unpack_mbap_header(&$buffer)
{
    assert(is_string($buffer));
    //tid,pid,len
    $tmp = unpack('ntid/npid/nlen', $buffer);
    $tmp['len'] -= 1; // remove uid size
    return $tmp;
}


// receive MODBUS TCP ADU and then callback response handler
function modbus_tcp_recv_adu($fp, &$response_handlers, &$error_msg)
{
    assert(is_resource($fp));
    assert(is_array($response_handlers));
    
    //read packet header
    $packed_header = '';
    if(!tcp_recv_until_done($fp, $packed_header, MBAP_HEADER_SIZE))
    {
        return false;
    }

    //check packet header
    $unpacked_header = unpack_mbap_header($packed_header);
    if(!check_mbap_header($unpacked_header))
    {
        return false;
    }
    
    //read PDU
    $packed_pdu = '';
    if(!tcp_recv_until_done($fp, $packed_pdu, $unpacked_header['len']))
    {
        return false;
    }
    
    //validate TID
    if(!isset($response_handlers[$unpacked_header['tid']]))
    {
        return false;
    }
    
    //callback response handler
    assert(isset($response_handlers[$unpacked_header['tid']]['handler']));
    assert(isset($response_handlers[$unpacked_header['tid']]['address']));
    assert(isset($response_handlers[$unpacked_header['tid']]['data']));
    return $response_handlers[$unpacked_header['tid']]['handler']($response_handlers[$unpacked_header['tid']]['address'], $packed_pdu, $error_msg, $response_handlers[$unpacked_header['tid']]['data']);
}


//send tcp packet until done it
function tcp_send_until_done($fp, &$buffer)
{
    assert(is_resource($fp) and is_string($buffer));
    
    $length = strlen($buffer);
    if($length == 0)
        return false;
        
    $count = 0;    
    while($length > $count)
    {
        $tmp = fwrite($fp, substr($buffer, $count, $length - $count));
        if(!$tmp)
            return false;
        $count += $tmp;
    }    
    return true;
}


//make MODBUS TCP Transaction
function modbus_tcp_transact($fp, &$commands, &$response_handlers, &$error_msg)
{
    assert(is_array($commands));
    //send modbus tcp request(s)
    for($i = 0; $i < count($commands); ++$i)
    {
        if(!tcp_send_until_done($fp, $commands[$i]))
        {
            return false;
        }
    }
    
    //receive modbus tcp response(s)
    for($i = 0; $i < count($commands); ++$i)
    {
        if(!modbus_tcp_recv_adu($fp, $response_handlers, $error_msg))
        {
            return false;
        }        
    }
    return true;
    
}


function read_holding_registers_0_handler($address, &$pdu, &$error_msg, &$data)
{
  assert(is_int($address));
  assert(is_string($pdu)); 
  assert(is_string($error_msg));  
  
  $exception = 0;
  if(!modbus_tcp_check_read_reg_response_pdu($pdu, READ_HOLDING_REGISTERS, $exception, 4))
  {
    if($exception == 0)
    {
        //invalid protocol
        return false;   
    }            
    //send back exception code
    return true;                
  }         
  
  $unpacked_pdu = unpack(MODBUS_TCP_UNPACK_FUNC_BYTECOUNT . '/ndata1low/ndata1high', $pdu);
  printf("holding register address %d-%d: %d\n", $address, $address+1, ($unpacked_pdu['data1high']<<16) | $unpacked_pdu['data1low']);
  return true;
}


function read_holding_registers_300_handler($address, &$pdu, &$error_msg, &$data)
{
  assert(is_int($address));
  assert(is_string($pdu)); 
  assert(is_string($error_msg));  
  
  $exception = 0;
  if(!modbus_tcp_check_read_reg_response_pdu($pdu, READ_HOLDING_REGISTERS, $exception, 4))
  {
    if($exception == 0)
    {
        //invalid protocol
        return false;   
    }            
    //send back exception code
    return true;                
  }         
  
  $unpacked_pdu = unpack(MODBUS_TCP_UNPACK_FUNC_BYTECOUNT . '/Ndata2', $pdu);
  printf("holding register address %d-%d: %d\n", $address, $address+1, $unpacked_pdu['data2']);
  return true;
}


function read_inpu_registers_0_handler($address, &$pdu, &$error_msg, &$data)
{
  assert(is_int($address));
  assert(is_string($pdu)); 
  assert(is_string($error_msg));  
  
  $exception = 0;
  if(!modbus_tcp_check_read_reg_response_pdu($pdu, READ_INPUT_REGISTERS, $exception, 4))
  {
    if($exception == 0)
    {
        //invalid protocol
        return false;   
    }            
    //send back exception code
    return true;                
  }         

  $unpacked_pdu = unpack(MODBUS_TCP_UNPACK_FUNC_BYTECOUNT . '/ndata3high/ndata3low', $pdu);
  $data3 = unpack('f', pack('SS', $unpacked_pdu['data3high'], $unpacked_pdu['data3low']));
  printf("input register address %d-%d: %f\n", $address, $address+1, $data3[1]);
  return true;
}


function read_inpu_registers_200_handler($address, &$pdu, &$error_msg, &$data)
{
  assert(is_int($address));
  assert(is_string($pdu)); 
  assert(is_string($error_msg));  
  
  $exception = 0;
  if(!modbus_tcp_check_read_reg_response_pdu($pdu, READ_INPUT_REGISTERS, $exception, 8))
  {
    if($exception == 0)
    {
        //invalid protocol
        return false;   
    }            
    //send back exception code
    return true;                
  }         

  $unpacked_pdu = unpack(MODBUS_TCP_UNPACK_FUNC_BYTECOUNT . '/nd3/nd2/nd1/nd0', $pdu);
  $data4 = unpack('d', pack('SSSS', $unpacked_pdu['d3'], $unpacked_pdu['d2'], $unpacked_pdu['d1'], $unpacked_pdu['d0']));
  printf("input register address %d-%d: %f\n", $address, $address+3, $data4[1]);
  return true;
}

//connect 127.0.0.1, port:502, timeout:3sec
$fp = @fsockopen('tcp://127.0.0.1', 502, $errno, $errstr, 3);
if(!$fp)
{
    printf("can\'t connect modbus tcp device\n");
    die();
}

//set send/receive timeout = 3sec
if(!stream_set_timeout($fp, 3))
{
    fclose($fp);
    printf("failed to change communication timeout\n");
    die();    
}

$tid = 0; //initialize TID
$test_commands = array();
$test_response_handlers = array();

//read holding register address:0 quantity:2 type: long
$test_commands[] = modbus_tcp_build_read_reg_cmd($tid, 1, READ_HOLDING_REGISTERS, 0, 2);
$response_handlers[$tid] = array();
$response_handlers[$tid]['address'] = 0;
$response_handlers[$tid]['handler'] = 'read_holding_registers_0_handler';

//read holding register address:300 quantity:2 type: long inverse
$test_commands[] = modbus_tcp_build_read_reg_cmd($tid, 1, READ_HOLDING_REGISTERS, 300, 2);
$response_handlers[$tid] = array();
$response_handlers[$tid]['address'] = 300;
$response_handlers[$tid]['handler'] = 'read_holding_registers_300_handler';

//read input register address:0 quantity:2 type: float
$test_commands[] = modbus_tcp_build_read_reg_cmd($tid, 1, READ_INPUT_REGISTERS, 0, 2);
$response_handlers[$tid] = array();
$response_handlers[$tid]['address'] = 0;
$response_handlers[$tid]['handler'] = 'read_inpu_registers_0_handler';

//read input register address:0 quantity:4 type: double
$test_commands[] = modbus_tcp_build_read_reg_cmd($tid, 1, READ_INPUT_REGISTERS, 200, 4);
$response_handlers[$tid] = array();
$response_handlers[$tid]['address'] = 200;
$response_handlers[$tid]['handler'] = 'read_inpu_registers_200_handler';

if(!modbus_tcp_transact($fp, $test_commands, $response_handlers))
{
  fclose($fp);
  printf("shit happen!\n");
  die();  
}

fclose($fp);

?>