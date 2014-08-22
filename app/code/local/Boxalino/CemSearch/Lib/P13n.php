<?php

class P13n extends AbstractThrift
{
    protected $dependencies = array(
        'P13nService.php',
        'Types.php',
    );
    protected $client = null;
    protected $transport = null;
//    protected $host = "https://cdn.bx-cloud.com/p13n.web/p13n";
    protected $host = "cdn.bx-cloud.com";
    protected $port = 80;

//    protected $port = 443;

    public function setHost($host)
    {
        $this->host = $host;
        $this->transport = null;
    }

    public function setPort($port)
    {
        $this->port = $port;
        $this->transport = null;
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this->getClient(), $name)) {
            return call_user_func_array(array($this->getClient(), $name), $arguments);
        } else {
            throw new Exception("Method $name not supported in P13nService");
        }
    }

    protected function getClient($clientId = '')
    {
        if ($this->client === null || $this->transport === null) {
            $this->transport = new \Thrift\Transport\P13nTHttpClient($this->host, $this->port, "/p13n.web/p13n", "https");
            $this->transport->setAuthorization('codete', 'oNaeGhahVoo7');
            $this->client = new \com\boxalino\p13n\api\thrift\P13nServiceClient(new \Thrift\Protocol\TCompactProtocol($this->transport));
            $this->transport->open();
        }
        return $this->client;
    }
}
