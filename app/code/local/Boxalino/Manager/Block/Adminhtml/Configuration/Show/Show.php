<?php

class Boxalino_Manager_Block_Adminhtml_Configuration_Show_Show extends Mage_Core_Block_Template
{
    public function getDifferences()
    {
        $differences  = $this->getData('differences');
        if(!empty($differences)) {
            $diffs = array();
            foreach ($differences as $difference) {
                if (empty($difference->contentId)) {
                    $difference->contentId = 'none';
                }
                $diffs[$difference->contentType][] = array('name' => $difference->contentId, 'parameter' => $difference->parameterName, 'old' => $difference->contentSource, 'new' => $difference->contentDestination);
            }
            return $diffs;
        } else {
            Mage::getModel('adminhtml/session')->addNotice("You don't have any changes");
            Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl('boxalino_manager/configuration'));
            Mage::app()->getResponse()->sendResponse();
            exit;
        }
    }

    public function getVersions()
    {
        $versions  = $this->getData('versions');
        if($versions) {
            $old = $versions[0];
            $new = $versions[1];

            return array('old' => $old, 'new' => $new);
        } else {
            return array('old' => 1, 'new' => 1);
        }
    }
}