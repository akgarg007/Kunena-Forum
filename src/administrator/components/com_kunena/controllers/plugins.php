<?php
/**
 * Kunena Component
 *
 * @package         Kunena.Administrator
 * @subpackage      Controllers
 *
 * @copyright       Copyright (C) 2008 - 2017 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/
defined('_JEXEC') or die();

/**
 * Kunena Plugins Controller
 *
 * @since  2.0
 */
class KunenaAdminControllerPlugins extends KunenaController
{
	/**
	 * @var null|string
	 * @since Kunena
	 */
	protected $baseurl = null;

	/**
	 * Construct
	 *
	 * @param   array $config config
	 *
	 * @throws Exception
	 *
	 * @since    2.0
	 */
	public function __construct($config = array())
	{
		$this->option = 'com_kunena';
		$this->input  = \Joomla\CMS\Factory::getApplication()->input;

		parent::__construct($config);
		$this->baseurl     = 'administrator/index.php?option=com_kunena&view=plugins';
		$this->baseurl2    = 'administrator/index.php?option=com_kunena&view=plugins';
		$this->view_list   = 'plugins';
		$this->text_prefix = 'COM_PLUGINS';

		// Value = 0
		$this->registerTask('unpublish', 'publish');

		// Value = 2
		$this->registerTask('archive', 'publish');

		// Value = -2
		$this->registerTask('trash', 'publish');

		// Value = -3
		$this->registerTask('report', 'publish');
		$this->registerTask('orderup', 'reorder');
		$this->registerTask('orderdown', 'reorder');

		\Joomla\CMS\Factory::getLanguage()->load('com_plugins', JPATH_ADMINISTRATOR);
	}

	/**
	 * Method to publish a list of items
	 *
	 * @return  void
	 *
	 * @throws Exception
	 * @since   12.2
	 */
	public function publish()
	{
		// Check for request forgeries
		\Joomla\CMS\Session\Session::checkToken() or die(JText::_('JINVALID_TOKEN'));

		// Get items to publish from the request.
		$cid   = \Joomla\CMS\Factory::getApplication()->input->get('cid', array(), 'array');
		$data  = array('publish' => 1, 'unpublish' => 0, 'archive' => 2, 'trash' => -2, 'report' => -3);
		$task  = $this->getTask();
		$value = Joomla\Utilities\ArrayHelper::getValue($data, $task, 0, 'int');

		if (empty($cid))
		{
			\Joomla\CMS\Log\Log::add(JText::_($this->text_prefix . '_NO_ITEM_SELECTED'), \Joomla\CMS\Log\Log::WARNING, 'jerror');
		}
		else
		{
			// Get the model.
			$model = $this->getModel();

			// Make sure the item ids are integers
			Joomla\Utilities\ArrayHelper::toInteger($cid);

			// Publish the items.
			if (!$model->publish($cid, $value))
			{
				\Joomla\CMS\Log\Log::add($model->getError(), \Joomla\CMS\Log\Log::WARNING, 'jerror');
			}
			else
			{
				if ($value == 1)
				{
					$ntext = $this->text_prefix . '_N_ITEMS_PUBLISHED';
				}
				elseif ($value == 0)
				{
					$ntext = $this->text_prefix . '_N_ITEMS_UNPUBLISHED';
				}
				elseif ($value == 2)
				{
					$ntext = $this->text_prefix . '_N_ITEMS_ARCHIVED';
				}
				else
				{
					$ntext = $this->text_prefix . '_N_ITEMS_TRASHED';
				}

				$this->setMessage(JText::plural($ntext, count($cid)));
			}
		}

		$editor = KunenaBbcodeEditor::getInstance();
		$editor->initializeHMVC();

		$extension    = $this->input->get('extension');
		$extensionURL = ($extension) ? '&extension=' . $extension : '';
		$this->setRedirect(JRoute::_('index.php?option=' . $this->option . '&view=' . $this->view_list . $extensionURL, false));
	}

	/**
	 * Getmodel
	 *
	 * @param   string $name   name
	 * @param   string $prefix prefix
	 * @param   array  $config config
	 *
	 * @return object
	 *
	 * @since    2.0
	 */
	public function getModel($name = '', $prefix = '', $config = array())
	{
		if (empty($name))
		{
			$name = 'plugin';
		}

		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * Changes the order of one or more records.
	 *
	 * @return  boolean  True on success
	 *
	 * @throws Exception
	 * @since   12.2
	 */
	public function reorder()
	{
		// Check for request forgeries.
		\Joomla\CMS\Session\Session::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$ids = \Joomla\CMS\Factory::getApplication()->input->post->get('cid', array(), 'array');
		$inc = ($this->getTask() == 'orderup') ? -1 : +1;

		$model  = $this->getModel();
		$return = $model->reorder($ids, $inc);

		if ($return === false)
		{
			// Reorder failed.
			$message = JText::sprintf('JLIB_APPLICATION_ERROR_REORDER_FAILED', $model->getError());
			$this->setRedirect(JRoute::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false), $message, 'error');

			return false;
		}
		else
		{
			// Reorder succeeded.
			$message = JText::_('JLIB_APPLICATION_SUCCESS_ITEM_REORDERED');
			$this->setRedirect(JRoute::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false), $message);

			return true;
		}
	}

	/**
	 * Method to save the submitted ordering values for records.
	 *
	 * @return  boolean  True on success
	 *
	 * @since   12.2
	 */
	public function saveorder()
	{
		// Check for request forgeries.
		\Joomla\CMS\Session\Session::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		// Get the input
		$pks   = $this->input->post->get('cid', array(), 'array');
		$order = $this->input->post->get('order', array(), 'array');

		// Sanitize the input
		Joomla\Utilities\ArrayHelper::toInteger($pks);
		Joomla\Utilities\ArrayHelper::toInteger($order);

		// Get the model
		$model = $this->getModel();

		// Save the ordering
		$return = $model->saveorder($pks, $order);

		if ($return === false)
		{
			// Reorder failed
			$message = JText::sprintf('JLIB_APPLICATION_ERROR_REORDER_FAILED', $model->getError());
			$this->setRedirect(JRoute::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false), $message, 'error');

			return false;
		}
		else
		{
			// Reorder succeeded.
			$this->setMessage(JText::_('JLIB_APPLICATION_SUCCESS_ORDERING_SAVED'));
			$this->setRedirect(JRoute::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false));

			return true;
		}
	}

	/**
	 * Check in of one or more records.
	 *
	 * @return  boolean  True on success
	 *
	 * @throws Exception
	 * @since   12.2
	 */
	public function checkin()
	{
		// Check for request forgeries.
		\Joomla\CMS\Session\Session::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$ids = \Joomla\CMS\Factory::getApplication()->input->post->get('cid', array(), 'array');

		$model  = $this->getModel();
		$return = $model->checkin($ids);

		if ($return === false)
		{
			// Checkin failed.
			$message = JText::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError());
			$this->setRedirect(JRoute::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false), $message, 'error');

			return false;
		}
		else
		{
			$editor = KunenaBbcodeEditor::getInstance();
			$editor->initializeHMVC();

			// Checkin succeeded.
			$message = JText::plural($this->text_prefix . '_N_ITEMS_CHECKED_IN', count($ids));
			$this->setRedirect(JRoute::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false), $message);

			return true;
		}
	}

	/**
	 * Regenerate editor file
	 *
	 * @since 5.0.2
	 */
	public function resync()
	{
		$editor = KunenaBbcodeEditor::getInstance();
		$editor->initializeHMVC();

		$message = 'Sync done';
		$this->setRedirect(JRoute::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false), $message);
	}
}
