<?php
/**
 * @category    Fishpig
 * @package    Fishpig_NoBots
 * @license      http://fishpig.co.uk/license.txt
 * @author       Ben Tideswell <ben@fishpig.co.uk>
 */

class Fishpig_NoBots_Block_Adminhtml_Bot extends Mage_Adminhtml_Block_Widget_Grid_Container
{
	/**
	 * Set the block options
	 *
	 * @return void
	 */
	public function __construct()
	{	
		parent::__construct();
		
		$this->_controller = 'adminhtml_bot';
		$this->_blockGroup = 'nobots';
		$this->_headerText = $this->__('NoBots');
		
		$this->_removeButton('add');
	}
}