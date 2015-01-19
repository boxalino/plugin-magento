<?php
namespace com\boxalino\p13n\api\thrift;
/**
 * Autogenerated by Thrift Compiler (0.9.1)
 *
 * DO NOT EDIT UNLESS YOU ARE SURE THAT YOU KNOW WHAT YOU ARE DOING
 *    @generated
 */
use Thrift\Base\TBase;
use Thrift\Type\TType;
use Thrift\Type\TMessageType;
use Thrift\Exception\TException;
use Thrift\Exception\TProtocolException;
use Thrift\Protocol\TProtocol;
use Thrift\Protocol\TBinaryProtocolAccelerated;
use Thrift\Exception\TApplicationException;


interface P13nServiceIf {
    public function choose(\com\boxalino\p13n\api\thrift\ChoiceRequest $choiceRequest);
    public function batchChoose(\com\boxalino\p13n\api\thrift\BatchChoiceRequest $batchChoiceRequest);
    public function autocomplete(\com\boxalino\p13n\api\thrift\AutocompleteRequest $request);
    public function updateChoice(\com\boxalino\p13n\api\thrift\ChoiceUpdateRequest $choiceUpdateRequest);
}

class P13nServiceClient implements \com\boxalino\p13n\api\thrift\P13nServiceIf {
    protected $input_ = null;
    protected $output_ = null;

    protected $seqid_ = 0;

    public function __construct($input, $output=null) {
        $this->input_ = $input;
        $this->output_ = $output ? $output : $input;
    }

    public function choose(\com\boxalino\p13n\api\thrift\ChoiceRequest $choiceRequest)
    {
        $this->send_choose($choiceRequest);
        return $this->recv_choose();
    }

    public function send_choose(\com\boxalino\p13n\api\thrift\ChoiceRequest $choiceRequest)
    {
        $args = new \com\boxalino\p13n\api\thrift\P13nService_choose_args();
        $args->choiceRequest = $choiceRequest;
        $bin_accel = ($this->output_ instanceof TBinaryProtocolAccelerated) && function_exists('thrift_protocol_write_binary');
        if ($bin_accel)
        {
            thrift_protocol_write_binary($this->output_, 'choose', TMessageType::CALL, $args, $this->seqid_, $this->output_->isStrictWrite());
        }
        else
        {
            $this->output_->writeMessageBegin('choose', TMessageType::CALL, $this->seqid_);
            $args->write($this->output_);
            $this->output_->writeMessageEnd();
            $this->output_->getTransport()->flush();
        }
    }

    public function recv_choose()
    {
        $bin_accel = ($this->input_ instanceof TBinaryProtocolAccelerated) && function_exists('thrift_protocol_read_binary');
        if ($bin_accel) $result = thrift_protocol_read_binary($this->input_, '\com\boxalino\p13n\api\thrift\P13nService_choose_result', $this->input_->isStrictRead());
        else
        {
            $rseqid = 0;
            $fname = null;
            $mtype = 0;

            $this->input_->readMessageBegin($fname, $mtype, $rseqid);
            if ($mtype == TMessageType::EXCEPTION) {
                $x = new TApplicationException();
                $x->read($this->input_);
                $this->input_->readMessageEnd();
                throw $x;
            }
            $result = new \com\boxalino\p13n\api\thrift\P13nService_choose_result();
            $result->read($this->input_);
            $this->input_->readMessageEnd();
        }
        if ($result->success !== null) {
            return $result->success;
        }
        if ($result->p13nServiceException !== null) {
            throw $result->p13nServiceException;
        }
        throw new \Exception("choose failed: unknown result");
    }

    public function batchChoose(\com\boxalino\p13n\api\thrift\BatchChoiceRequest $batchChoiceRequest)
    {
        $this->send_batchChoose($batchChoiceRequest);
        return $this->recv_batchChoose();
    }

    public function send_batchChoose(\com\boxalino\p13n\api\thrift\BatchChoiceRequest $batchChoiceRequest)
    {
        $args = new \com\boxalino\p13n\api\thrift\P13nService_batchChoose_args();
        $args->batchChoiceRequest = $batchChoiceRequest;
        $bin_accel = ($this->output_ instanceof TBinaryProtocolAccelerated) && function_exists('thrift_protocol_write_binary');
        if ($bin_accel)
        {
            thrift_protocol_write_binary($this->output_, 'batchChoose', TMessageType::CALL, $args, $this->seqid_, $this->output_->isStrictWrite());
        }
        else
        {
            $this->output_->writeMessageBegin('batchChoose', TMessageType::CALL, $this->seqid_);
            $args->write($this->output_);
            $this->output_->writeMessageEnd();
            $this->output_->getTransport()->flush();
        }
    }

    public function recv_batchChoose()
    {
        $bin_accel = ($this->input_ instanceof TBinaryProtocolAccelerated) && function_exists('thrift_protocol_read_binary');
        if ($bin_accel) $result = thrift_protocol_read_binary($this->input_, '\com\boxalino\p13n\api\thrift\P13nService_batchChoose_result', $this->input_->isStrictRead());
        else
        {
            $rseqid = 0;
            $fname = null;
            $mtype = 0;

            $this->input_->readMessageBegin($fname, $mtype, $rseqid);
            if ($mtype == TMessageType::EXCEPTION) {
                $x = new TApplicationException();
                $x->read($this->input_);
                $this->input_->readMessageEnd();
                throw $x;
            }
            $result = new \com\boxalino\p13n\api\thrift\P13nService_batchChoose_result();
            $result->read($this->input_);
            $this->input_->readMessageEnd();
        }
        if ($result->success !== null) {
            return $result->success;
        }
        if ($result->p13nServiceException !== null) {
            throw $result->p13nServiceException;
        }
        throw new \Exception("batchChoose failed: unknown result");
    }

    public function autocomplete(\com\boxalino\p13n\api\thrift\AutocompleteRequest $request)
    {
        $this->send_autocomplete($request);
        return $this->recv_autocomplete();
    }

    public function send_autocomplete(\com\boxalino\p13n\api\thrift\AutocompleteRequest $request)
    {
        $args = new \com\boxalino\p13n\api\thrift\P13nService_autocomplete_args();
        $args->request = $request;
        $bin_accel = ($this->output_ instanceof TBinaryProtocolAccelerated) && function_exists('thrift_protocol_write_binary');
        if ($bin_accel)
        {
            thrift_protocol_write_binary($this->output_, 'autocomplete', TMessageType::CALL, $args, $this->seqid_, $this->output_->isStrictWrite());
        }
        else
        {
            $this->output_->writeMessageBegin('autocomplete', TMessageType::CALL, $this->seqid_);
            $args->write($this->output_);
            $this->output_->writeMessageEnd();
            $this->output_->getTransport()->flush();
        }
    }

    public function recv_autocomplete()
    {
        $bin_accel = ($this->input_ instanceof TBinaryProtocolAccelerated) && function_exists('thrift_protocol_read_binary');
        if ($bin_accel) $result = thrift_protocol_read_binary($this->input_, '\com\boxalino\p13n\api\thrift\P13nService_autocomplete_result', $this->input_->isStrictRead());
        else
        {
            $rseqid = 0;
            $fname = null;
            $mtype = 0;

            $this->input_->readMessageBegin($fname, $mtype, $rseqid);
            if ($mtype == TMessageType::EXCEPTION) {
                $x = new TApplicationException();
                $x->read($this->input_);
                $this->input_->readMessageEnd();
                throw $x;
            }
            $result = new \com\boxalino\p13n\api\thrift\P13nService_autocomplete_result();
            $result->read($this->input_);
            $this->input_->readMessageEnd();
        }
        if ($result->success !== null) {
            return $result->success;
        }
        if ($result->p13nServiceException !== null) {
            throw $result->p13nServiceException;
        }
        throw new \Exception("autocomplete failed: unknown result");
    }

    public function updateChoice(\com\boxalino\p13n\api\thrift\ChoiceUpdateRequest $choiceUpdateRequest)
    {
        $this->send_updateChoice($choiceUpdateRequest);
        return $this->recv_updateChoice();
    }

    public function send_updateChoice(\com\boxalino\p13n\api\thrift\ChoiceUpdateRequest $choiceUpdateRequest)
    {
        $args = new \com\boxalino\p13n\api\thrift\P13nService_updateChoice_args();
        $args->choiceUpdateRequest = $choiceUpdateRequest;
        $bin_accel = ($this->output_ instanceof TBinaryProtocolAccelerated) && function_exists('thrift_protocol_write_binary');
        if ($bin_accel)
        {
            thrift_protocol_write_binary($this->output_, 'updateChoice', TMessageType::CALL, $args, $this->seqid_, $this->output_->isStrictWrite());
        }
        else
        {
            $this->output_->writeMessageBegin('updateChoice', TMessageType::CALL, $this->seqid_);
            $args->write($this->output_);
            $this->output_->writeMessageEnd();
            $this->output_->getTransport()->flush();
        }
    }

    public function recv_updateChoice()
    {
        $bin_accel = ($this->input_ instanceof TBinaryProtocolAccelerated) && function_exists('thrift_protocol_read_binary');
        if ($bin_accel) $result = thrift_protocol_read_binary($this->input_, '\com\boxalino\p13n\api\thrift\P13nService_updateChoice_result', $this->input_->isStrictRead());
        else
        {
            $rseqid = 0;
            $fname = null;
            $mtype = 0;

            $this->input_->readMessageBegin($fname, $mtype, $rseqid);
            if ($mtype == TMessageType::EXCEPTION) {
                $x = new TApplicationException();
                $x->read($this->input_);
                $this->input_->readMessageEnd();
                throw $x;
            }
            $result = new \com\boxalino\p13n\api\thrift\P13nService_updateChoice_result();
            $result->read($this->input_);
            $this->input_->readMessageEnd();
        }
        if ($result->success !== null) {
            return $result->success;
        }
        if ($result->p13nServiceException !== null) {
            throw $result->p13nServiceException;
        }
        throw new \Exception("updateChoice failed: unknown result");
    }

}

// HELPER FUNCTIONS AND STRUCTURES

class P13nService_choose_args {
    static $_TSPEC;

    public $choiceRequest = null;

    public function __construct($vals=null) {
        if (!isset(self::$_TSPEC)) {
            self::$_TSPEC = array(
                -1 => array(
                    'var' => 'choiceRequest',
                    'type' => TType::STRUCT,
                    'class' => '\com\boxalino\p13n\api\thrift\ChoiceRequest',
                ),
            );
        }
        if (is_array($vals)) {
            if (isset($vals['choiceRequest'])) {
                $this->choiceRequest = $vals['choiceRequest'];
            }
        }
    }

    public function getName() {
        return 'P13nService_choose_args';
    }

    public function read($input)
    {
        $xfer = 0;
        $fname = null;
        $ftype = 0;
        $fid = 0;
        $xfer += $input->readStructBegin($fname);
        while (true)
        {
            $xfer += $input->readFieldBegin($fname, $ftype, $fid);
            if ($ftype == TType::STOP) {
                break;
            }
            switch ($fid)
            {
                case -1:
                    if ($ftype == TType::STRUCT) {
                        $this->choiceRequest = new \com\boxalino\p13n\api\thrift\ChoiceRequest();
                        $xfer += $this->choiceRequest->read($input);
                    } else {
                        $xfer += $input->skip($ftype);
                    }
                    break;
                default:
                    $xfer += $input->skip($ftype);
                    break;
            }
            $xfer += $input->readFieldEnd();
        }
        $xfer += $input->readStructEnd();
        return $xfer;
    }

    public function write($output) {
        $xfer = 0;
        $xfer += $output->writeStructBegin('P13nService_choose_args');
        if ($this->choiceRequest !== null) {
            if (!is_object($this->choiceRequest)) {
                throw new TProtocolException('Bad type in structure.', TProtocolException::INVALID_DATA);
            }
            $xfer += $output->writeFieldBegin('choiceRequest', TType::STRUCT, -1);
            $xfer += $this->choiceRequest->write($output);
            $xfer += $output->writeFieldEnd();
        }
        $xfer += $output->writeFieldStop();
        $xfer += $output->writeStructEnd();
        return $xfer;
    }

}

class P13nService_choose_result {
    static $_TSPEC;

    public $success = null;
    public $p13nServiceException = null;

    public function __construct($vals=null) {
        if (!isset(self::$_TSPEC)) {
            self::$_TSPEC = array(
                0 => array(
                    'var' => 'success',
                    'type' => TType::STRUCT,
                    'class' => '\com\boxalino\p13n\api\thrift\ChoiceResponse',
                ),
                1 => array(
                    'var' => 'p13nServiceException',
                    'type' => TType::STRUCT,
                    'class' => '\com\boxalino\p13n\api\thrift\P13nServiceException',
                ),
            );
        }
        if (is_array($vals)) {
            if (isset($vals['success'])) {
                $this->success = $vals['success'];
            }
            if (isset($vals['p13nServiceException'])) {
                $this->p13nServiceException = $vals['p13nServiceException'];
            }
        }
    }

    public function getName() {
        return 'P13nService_choose_result';
    }

    public function read($input)
    {
        $xfer = 0;
        $fname = null;
        $ftype = 0;
        $fid = 0;
        $xfer += $input->readStructBegin($fname);
        while (true)
        {
            $xfer += $input->readFieldBegin($fname, $ftype, $fid);
            if ($ftype == TType::STOP) {
                break;
            }
            switch ($fid)
            {
                case 0:
                    if ($ftype == TType::STRUCT) {
                        $this->success = new \com\boxalino\p13n\api\thrift\ChoiceResponse();
                        $xfer += $this->success->read($input);
                    } else {
                        $xfer += $input->skip($ftype);
                    }
                    break;
                case 1:
                    if ($ftype == TType::STRUCT) {
                        $this->p13nServiceException = new \com\boxalino\p13n\api\thrift\P13nServiceException();
                        $xfer += $this->p13nServiceException->read($input);
                    } else {
                        $xfer += $input->skip($ftype);
                    }
                    break;
                default:
                    $xfer += $input->skip($ftype);
                    break;
            }
            $xfer += $input->readFieldEnd();
        }
        $xfer += $input->readStructEnd();
        return $xfer;
    }

    public function write($output) {
        $xfer = 0;
        $xfer += $output->writeStructBegin('P13nService_choose_result');
        if ($this->success !== null) {
            if (!is_object($this->success)) {
                throw new TProtocolException('Bad type in structure.', TProtocolException::INVALID_DATA);
            }
            $xfer += $output->writeFieldBegin('success', TType::STRUCT, 0);
            $xfer += $this->success->write($output);
            $xfer += $output->writeFieldEnd();
        }
        if ($this->p13nServiceException !== null) {
            $xfer += $output->writeFieldBegin('p13nServiceException', TType::STRUCT, 1);
            $xfer += $this->p13nServiceException->write($output);
            $xfer += $output->writeFieldEnd();
        }
        $xfer += $output->writeFieldStop();
        $xfer += $output->writeStructEnd();
        return $xfer;
    }

}

class P13nService_batchChoose_args {
    static $_TSPEC;

    public $batchChoiceRequest = null;

    public function __construct($vals=null) {
        if (!isset(self::$_TSPEC)) {
            self::$_TSPEC = array(
                -1 => array(
                    'var' => 'batchChoiceRequest',
                    'type' => TType::STRUCT,
                    'class' => '\com\boxalino\p13n\api\thrift\BatchChoiceRequest',
                ),
            );
        }
        if (is_array($vals)) {
            if (isset($vals['batchChoiceRequest'])) {
                $this->batchChoiceRequest = $vals['batchChoiceRequest'];
            }
        }
    }

    public function getName() {
        return 'P13nService_batchChoose_args';
    }

    public function read($input)
    {
        $xfer = 0;
        $fname = null;
        $ftype = 0;
        $fid = 0;
        $xfer += $input->readStructBegin($fname);
        while (true)
        {
            $xfer += $input->readFieldBegin($fname, $ftype, $fid);
            if ($ftype == TType::STOP) {
                break;
            }
            switch ($fid)
            {
                case -1:
                    if ($ftype == TType::STRUCT) {
                        $this->batchChoiceRequest = new \com\boxalino\p13n\api\thrift\BatchChoiceRequest();
                        $xfer += $this->batchChoiceRequest->read($input);
                    } else {
                        $xfer += $input->skip($ftype);
                    }
                    break;
                default:
                    $xfer += $input->skip($ftype);
                    break;
            }
            $xfer += $input->readFieldEnd();
        }
        $xfer += $input->readStructEnd();
        return $xfer;
    }

    public function write($output) {
        $xfer = 0;
        $xfer += $output->writeStructBegin('P13nService_batchChoose_args');
        if ($this->batchChoiceRequest !== null) {
            if (!is_object($this->batchChoiceRequest)) {
                throw new TProtocolException('Bad type in structure.', TProtocolException::INVALID_DATA);
            }
            $xfer += $output->writeFieldBegin('batchChoiceRequest', TType::STRUCT, -1);
            $xfer += $this->batchChoiceRequest->write($output);
            $xfer += $output->writeFieldEnd();
        }
        $xfer += $output->writeFieldStop();
        $xfer += $output->writeStructEnd();
        return $xfer;
    }

}

class P13nService_batchChoose_result {
    static $_TSPEC;

    public $success = null;
    public $p13nServiceException = null;

    public function __construct($vals=null) {
        if (!isset(self::$_TSPEC)) {
            self::$_TSPEC = array(
                0 => array(
                    'var' => 'success',
                    'type' => TType::STRUCT,
                    'class' => '\com\boxalino\p13n\api\thrift\BatchChoiceResponse',
                ),
                1 => array(
                    'var' => 'p13nServiceException',
                    'type' => TType::STRUCT,
                    'class' => '\com\boxalino\p13n\api\thrift\P13nServiceException',
                ),
            );
        }
        if (is_array($vals)) {
            if (isset($vals['success'])) {
                $this->success = $vals['success'];
            }
            if (isset($vals['p13nServiceException'])) {
                $this->p13nServiceException = $vals['p13nServiceException'];
            }
        }
    }

    public function getName() {
        return 'P13nService_batchChoose_result';
    }

    public function read($input)
    {
        $xfer = 0;
        $fname = null;
        $ftype = 0;
        $fid = 0;
        $xfer += $input->readStructBegin($fname);
        while (true)
        {
            $xfer += $input->readFieldBegin($fname, $ftype, $fid);
            if ($ftype == TType::STOP) {
                break;
            }
            switch ($fid)
            {
                case 0:
                    if ($ftype == TType::STRUCT) {
                        $this->success = new \com\boxalino\p13n\api\thrift\BatchChoiceResponse();
                        $xfer += $this->success->read($input);
                    } else {
                        $xfer += $input->skip($ftype);
                    }
                    break;
                case 1:
                    if ($ftype == TType::STRUCT) {
                        $this->p13nServiceException = new \com\boxalino\p13n\api\thrift\P13nServiceException();
                        $xfer += $this->p13nServiceException->read($input);
                    } else {
                        $xfer += $input->skip($ftype);
                    }
                    break;
                default:
                    $xfer += $input->skip($ftype);
                    break;
            }
            $xfer += $input->readFieldEnd();
        }
        $xfer += $input->readStructEnd();
        return $xfer;
    }

    public function write($output) {
        $xfer = 0;
        $xfer += $output->writeStructBegin('P13nService_batchChoose_result');
        if ($this->success !== null) {
            if (!is_object($this->success)) {
                throw new TProtocolException('Bad type in structure.', TProtocolException::INVALID_DATA);
            }
            $xfer += $output->writeFieldBegin('success', TType::STRUCT, 0);
            $xfer += $this->success->write($output);
            $xfer += $output->writeFieldEnd();
        }
        if ($this->p13nServiceException !== null) {
            $xfer += $output->writeFieldBegin('p13nServiceException', TType::STRUCT, 1);
            $xfer += $this->p13nServiceException->write($output);
            $xfer += $output->writeFieldEnd();
        }
        $xfer += $output->writeFieldStop();
        $xfer += $output->writeStructEnd();
        return $xfer;
    }

}

class P13nService_autocomplete_args {
    static $_TSPEC;

    public $request = null;

    public function __construct($vals=null) {
        if (!isset(self::$_TSPEC)) {
            self::$_TSPEC = array(
                -1 => array(
                    'var' => 'request',
                    'type' => TType::STRUCT,
                    'class' => '\com\boxalino\p13n\api\thrift\AutocompleteRequest',
                ),
            );
        }
        if (is_array($vals)) {
            if (isset($vals['request'])) {
                $this->request = $vals['request'];
            }
        }
    }

    public function getName() {
        return 'P13nService_autocomplete_args';
    }

    public function read($input)
    {
        $xfer = 0;
        $fname = null;
        $ftype = 0;
        $fid = 0;
        $xfer += $input->readStructBegin($fname);
        while (true)
        {
            $xfer += $input->readFieldBegin($fname, $ftype, $fid);
            if ($ftype == TType::STOP) {
                break;
            }
            switch ($fid)
            {
                case -1:
                    if ($ftype == TType::STRUCT) {
                        $this->request = new \com\boxalino\p13n\api\thrift\AutocompleteRequest();
                        $xfer += $this->request->read($input);
                    } else {
                        $xfer += $input->skip($ftype);
                    }
                    break;
                default:
                    $xfer += $input->skip($ftype);
                    break;
            }
            $xfer += $input->readFieldEnd();
        }
        $xfer += $input->readStructEnd();
        return $xfer;
    }

    public function write($output) {
        $xfer = 0;
        $xfer += $output->writeStructBegin('P13nService_autocomplete_args');
        if ($this->request !== null) {
            if (!is_object($this->request)) {
                throw new TProtocolException('Bad type in structure.', TProtocolException::INVALID_DATA);
            }
            $xfer += $output->writeFieldBegin('request', TType::STRUCT, -1);
            $xfer += $this->request->write($output);
            $xfer += $output->writeFieldEnd();
        }
        $xfer += $output->writeFieldStop();
        $xfer += $output->writeStructEnd();
        return $xfer;
    }

}

class P13nService_autocomplete_result {
    static $_TSPEC;

    public $success = null;
    public $p13nServiceException = null;

    public function __construct($vals=null) {
        if (!isset(self::$_TSPEC)) {
            self::$_TSPEC = array(
                0 => array(
                    'var' => 'success',
                    'type' => TType::STRUCT,
                    'class' => '\com\boxalino\p13n\api\thrift\AutocompleteResponse',
                ),
                1 => array(
                    'var' => 'p13nServiceException',
                    'type' => TType::STRUCT,
                    'class' => '\com\boxalino\p13n\api\thrift\P13nServiceException',
                ),
            );
        }
        if (is_array($vals)) {
            if (isset($vals['success'])) {
                $this->success = $vals['success'];
            }
            if (isset($vals['p13nServiceException'])) {
                $this->p13nServiceException = $vals['p13nServiceException'];
            }
        }
    }

    public function getName() {
        return 'P13nService_autocomplete_result';
    }

    public function read($input)
    {
        $xfer = 0;
        $fname = null;
        $ftype = 0;
        $fid = 0;
        $xfer += $input->readStructBegin($fname);
        while (true)
        {
            $xfer += $input->readFieldBegin($fname, $ftype, $fid);
            if ($ftype == TType::STOP) {
                break;
            }
            switch ($fid)
            {
                case 0:
                    if ($ftype == TType::STRUCT) {
                        $this->success = new \com\boxalino\p13n\api\thrift\AutocompleteResponse();
                        $xfer += $this->success->read($input);
                    } else {
                        $xfer += $input->skip($ftype);
                    }
                    break;
                case 1:
                    if ($ftype == TType::STRUCT) {
                        $this->p13nServiceException = new \com\boxalino\p13n\api\thrift\P13nServiceException();
                        $xfer += $this->p13nServiceException->read($input);
                    } else {
                        $xfer += $input->skip($ftype);
                    }
                    break;
                default:
                    $xfer += $input->skip($ftype);
                    break;
            }
            $xfer += $input->readFieldEnd();
        }
        $xfer += $input->readStructEnd();
        return $xfer;
    }

    public function write($output) {
        $xfer = 0;
        $xfer += $output->writeStructBegin('P13nService_autocomplete_result');
        if ($this->success !== null) {
            if (!is_object($this->success)) {
                throw new TProtocolException('Bad type in structure.', TProtocolException::INVALID_DATA);
            }
            $xfer += $output->writeFieldBegin('success', TType::STRUCT, 0);
            $xfer += $this->success->write($output);
            $xfer += $output->writeFieldEnd();
        }
        if ($this->p13nServiceException !== null) {
            $xfer += $output->writeFieldBegin('p13nServiceException', TType::STRUCT, 1);
            $xfer += $this->p13nServiceException->write($output);
            $xfer += $output->writeFieldEnd();
        }
        $xfer += $output->writeFieldStop();
        $xfer += $output->writeStructEnd();
        return $xfer;
    }

}

class P13nService_updateChoice_args {
    static $_TSPEC;

    public $choiceUpdateRequest = null;

    public function __construct($vals=null) {
        if (!isset(self::$_TSPEC)) {
            self::$_TSPEC = array(
                -1 => array(
                    'var' => 'choiceUpdateRequest',
                    'type' => TType::STRUCT,
                    'class' => '\com\boxalino\p13n\api\thrift\ChoiceUpdateRequest',
                ),
            );
        }
        if (is_array($vals)) {
            if (isset($vals['choiceUpdateRequest'])) {
                $this->choiceUpdateRequest = $vals['choiceUpdateRequest'];
            }
        }
    }

    public function getName() {
        return 'P13nService_updateChoice_args';
    }

    public function read($input)
    {
        $xfer = 0;
        $fname = null;
        $ftype = 0;
        $fid = 0;
        $xfer += $input->readStructBegin($fname);
        while (true)
        {
            $xfer += $input->readFieldBegin($fname, $ftype, $fid);
            if ($ftype == TType::STOP) {
                break;
            }
            switch ($fid)
            {
                case -1:
                    if ($ftype == TType::STRUCT) {
                        $this->choiceUpdateRequest = new \com\boxalino\p13n\api\thrift\ChoiceUpdateRequest();
                        $xfer += $this->choiceUpdateRequest->read($input);
                    } else {
                        $xfer += $input->skip($ftype);
                    }
                    break;
                default:
                    $xfer += $input->skip($ftype);
                    break;
            }
            $xfer += $input->readFieldEnd();
        }
        $xfer += $input->readStructEnd();
        return $xfer;
    }

    public function write($output) {
        $xfer = 0;
        $xfer += $output->writeStructBegin('P13nService_updateChoice_args');
        if ($this->choiceUpdateRequest !== null) {
            if (!is_object($this->choiceUpdateRequest)) {
                throw new TProtocolException('Bad type in structure.', TProtocolException::INVALID_DATA);
            }
            $xfer += $output->writeFieldBegin('choiceUpdateRequest', TType::STRUCT, -1);
            $xfer += $this->choiceUpdateRequest->write($output);
            $xfer += $output->writeFieldEnd();
        }
        $xfer += $output->writeFieldStop();
        $xfer += $output->writeStructEnd();
        return $xfer;
    }

}

class P13nService_updateChoice_result {
    static $_TSPEC;

    public $success = null;
    public $p13nServiceException = null;

    public function __construct($vals=null) {
        if (!isset(self::$_TSPEC)) {
            self::$_TSPEC = array(
                0 => array(
                    'var' => 'success',
                    'type' => TType::STRUCT,
                    'class' => '\com\boxalino\p13n\api\thrift\ChoiceUpdateResponse',
                ),
                1 => array(
                    'var' => 'p13nServiceException',
                    'type' => TType::STRUCT,
                    'class' => '\com\boxalino\p13n\api\thrift\P13nServiceException',
                ),
            );
        }
        if (is_array($vals)) {
            if (isset($vals['success'])) {
                $this->success = $vals['success'];
            }
            if (isset($vals['p13nServiceException'])) {
                $this->p13nServiceException = $vals['p13nServiceException'];
            }
        }
    }

    public function getName() {
        return 'P13nService_updateChoice_result';
    }

    public function read($input)
    {
        $xfer = 0;
        $fname = null;
        $ftype = 0;
        $fid = 0;
        $xfer += $input->readStructBegin($fname);
        while (true)
        {
            $xfer += $input->readFieldBegin($fname, $ftype, $fid);
            if ($ftype == TType::STOP) {
                break;
            }
            switch ($fid)
            {
                case 0:
                    if ($ftype == TType::STRUCT) {
                        $this->success = new \com\boxalino\p13n\api\thrift\ChoiceUpdateResponse();
                        $xfer += $this->success->read($input);
                    } else {
                        $xfer += $input->skip($ftype);
                    }
                    break;
                case 1:
                    if ($ftype == TType::STRUCT) {
                        $this->p13nServiceException = new \com\boxalino\p13n\api\thrift\P13nServiceException();
                        $xfer += $this->p13nServiceException->read($input);
                    } else {
                        $xfer += $input->skip($ftype);
                    }
                    break;
                default:
                    $xfer += $input->skip($ftype);
                    break;
            }
            $xfer += $input->readFieldEnd();
        }
        $xfer += $input->readStructEnd();
        return $xfer;
    }

    public function write($output) {
        $xfer = 0;
        $xfer += $output->writeStructBegin('P13nService_updateChoice_result');
        if ($this->success !== null) {
            if (!is_object($this->success)) {
                throw new TProtocolException('Bad type in structure.', TProtocolException::INVALID_DATA);
            }
            $xfer += $output->writeFieldBegin('success', TType::STRUCT, 0);
            $xfer += $this->success->write($output);
            $xfer += $output->writeFieldEnd();
        }
        if ($this->p13nServiceException !== null) {
            $xfer += $output->writeFieldBegin('p13nServiceException', TType::STRUCT, 1);
            $xfer += $this->p13nServiceException->write($output);
            $xfer += $output->writeFieldEnd();
        }
        $xfer += $output->writeFieldStop();
        $xfer += $output->writeStructEnd();
        return $xfer;
    }

}


