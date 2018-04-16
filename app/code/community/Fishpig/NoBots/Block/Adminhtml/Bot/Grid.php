<?php
/**
 * @category    Fishpig
 * @package    Fishpig_NoBots
 * @license      http://fishpig.co.uk/license.txt
 * @author       Ben Tideswell <ben@fishpig.co.uk>
 */
 
class Fishpig_NoBots_Block_Adminhtml_Bot_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
	/**
	 * Set the grid block options
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
		
		$this->setId('nobots_bot_grid');
		$this->setDefaultSort('created_at');
		$this->setDefaultDir('desc');
		$this->setSaveParametersInSession(false);
	}
	
	/**
	 * Initialise and set the collection for the grid
	 *
	 */
	protected function _prepareCollection()
	{
		$this->setCollection(
			Mage::getResourceModel('nobots/bot_collection')
		);
	
		return parent::_prepareCollection();
	}
    
	/**
	 * Add the columns to the grid
	 *
	 */
	protected function _prepareColumns()
	{
		$this->addColumn('bot_id', array(
			'header'	=> $this->__('ID'),
			'align'		=> 'left',
			'width'		=> '60px',
			'index'		=> 'bot_id',
		));
		
		$this->addColumn('ip', array(
			'header'	=> $this->__('IP'),
			'align'		=> 'left',
			'index'		=> 'ip',
		));

		$this->addColumn('first_activity_created_at', array(
			'header' => Mage::helper('cms')->__('First Seen'),
			'index' => 'first_activity_created_at',
			'type' => 'datetime',
			'sortable' => false,
			'filter' => false,
		));

		$this->addColumn('action',
			array(
				'width'     => '50px',
				'type'      => 'action',
				'getter'     => 'getId',
				'actions'   => array(array(
					'caption' => Mage::helper('catalog')->__('Delete'),
					'url'     => array('base'=>'*/*/delete'),
					'field'   => 'id'
				)),
				'filter'    => false,
				'sortable'  => false,
				'align' 	=> 'center',
			));

		return parent::_prepareColumns();
	}

	/**
	 * Add store information to pages
	 *
	 * @return $this
	 */
	protected function _afterLoadCollection()
	{
		foreach($this->getCollection()->getItems() as $item) {
			$item->loadFirstActivity();
		}

		parent::_afterLoadCollection();
	}
	
	/**
	 * Retrieve the URL for the row
	 *
	 */
	public function getRowUrl($row)
	{
		return '';
	}
}
