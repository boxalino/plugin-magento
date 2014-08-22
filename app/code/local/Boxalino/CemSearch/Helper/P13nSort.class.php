<?php

/**
 * User: Michal Sordyl
 * Mail: michal.sordyl@codete.co
 * Date: 02.06.14
 */
class P13nSort
{
    private $sorts = array();

    /**
     * @param array $sorts array(array('fieldName' => , 'reverse' => ), ..... )
     */
    public function __construct($sorts = array())
    {
        foreach ($sorts as $sort) {
            $this->push($sort['fieldName'], $sort['order']);
        }
    }

    /**
     * @param $field name od field to sort by
     * @param $reverse true for ASC, false for DESC
     */
    public function push($field, $reverse)
    {
        $this->sorts[] = array('fieldName' => $field, 'reverse' => $reverse);
    }

    public function getSorts()
    {
        return $this->sorts;
    }

}