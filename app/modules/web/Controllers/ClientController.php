<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Modules\Web\Controllers;


use SP\Core\Acl\Acl;
use SP\Core\Events\Event;
use SP\Core\Events\EventMessage;
use SP\Core\Exceptions\ValidationException;
use SP\DataModel\ClientData;
use SP\Http\JsonResponse;
use SP\Modules\Web\Controllers\Helpers\Grid\ClientGrid;
use SP\Modules\Web\Controllers\Traits\ItemTrait;
use SP\Modules\Web\Controllers\Traits\JsonTrait;
use SP\Modules\Web\Forms\ClientForm;
use SP\Mvc\Controller\CrudControllerInterface;
use SP\Services\Client\ClientService;

/**
 * Class ClientController
 *
 * @package SP\Modules\Web\Controllers
 */
final class ClientController extends ControllerBase implements CrudControllerInterface
{
    use JsonTrait, ItemTrait;

    /**
     * @var ClientService
     */
    protected $clientService;

    /**
     * Search action
     *
     * @return bool
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     * @throws \SP\Core\Exceptions\SPException
     */
    public function searchAction()
    {
        $this->checkSecurityToken($this->previousSk, $this->request);

        if (!$this->acl->checkUserAccess(Acl::CLIENT_SEARCH)) {
            return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('You don\'t have permission to do this operation'));
        }

        $this->view->addTemplate('datagrid-table', 'grid');
        $this->view->assign('index', $this->request->analyzeInt('activetab', 0));
        $this->view->assign('data', $this->getSearchGrid());

        return $this->returnJsonResponseData(['html' => $this->render()]);
    }

    /**
     * getSearchGrid
     *
     * @return $this
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    protected function getSearchGrid()
    {
        $itemSearchData = $this->getSearchData($this->configData->getAccountCount(), $this->request);

        $clientGrid = $this->dic->get(ClientGrid::class);

        return $clientGrid->updatePager($clientGrid->getGrid($this->clientService->search($itemSearchData)), $itemSearchData);
    }

    /**
     * Create action
     */
    public function createAction()
    {
        try {
            $this->checkSecurityToken($this->previousSk, $this->request);

            if (!$this->acl->checkUserAccess(Acl::CLIENT_CREATE)) {
                return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('You don\'t have permission to do this operation'));
            }

            $this->view->assign('header', __('New Client'));
            $this->view->assign('isView', false);
            $this->view->assign('route', 'client/saveCreate');

            $this->setViewData();

            $this->eventDispatcher->notifyEvent('show.client.create', new Event($this));

            return $this->returnJsonResponseData(['html' => $this->render()]);
        } catch (\Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent('exception', new Event($e));

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Sets view data for displaying client's data
     *
     * @param $clientId
     *
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     * @throws \SP\Services\ServiceException
     * @throws \SP\Repositories\NoSuchItemException
     */
    protected function setViewData($clientId = null)
    {
        $this->view->addTemplate('client', 'itemshow');

        $client = $clientId ? $this->clientService->getById($clientId) : new ClientData();

        $this->view->assign('client', $client);

        $this->view->assign('nextAction', Acl::getActionRoute(Acl::ITEMS_MANAGE));

        if ($this->view->isView === true) {
            $this->view->assign('disabled', 'disabled');
            $this->view->assign('readonly', 'readonly');
        } else {
            $this->view->assign('disabled', false);
            $this->view->assign('readonly', false);
        }

        $this->view->assign('showViewCustomPass', $this->acl->checkUserAccess(Acl::CUSTOMFIELD_VIEW_PASS));
        $this->view->assign('customFields', $this->getCustomFieldsForItem(Acl::CLIENT, $clientId));
    }

    /**
     * Edit action
     *
     * @param $id
     *
     * @return bool
     */
    public function editAction($id)
    {
        try {
            $this->checkSecurityToken($this->previousSk, $this->request);

            if (!$this->acl->checkUserAccess(Acl::CLIENT_EDIT)) {
                return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('You don\'t have permission to do this operation'));
            }

            $this->view->assign('header', __('Edit Client'));
            $this->view->assign('isView', false);
            $this->view->assign('route', 'client/saveEdit/' . $id);

            $this->setViewData($id);

            $this->eventDispatcher->notifyEvent('show.client.edit', new Event($this));

            return $this->returnJsonResponseData(['html' => $this->render()]);
        } catch (\Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent('exception', new Event($e));

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Delete action
     *
     * @param $id
     *
     * @return bool
     */
    public function deleteAction($id = null)
    {
        try {
            $this->checkSecurityToken($this->previousSk, $this->request);

            if (!$this->acl->checkUserAccess(Acl::CLIENT_DELETE)) {
                return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('You don\'t have permission to do this operation'));
            }

            if ($id === null) {
                $this->clientService->deleteByIdBatch($this->getItemsIdFromRequest($this->request));

                $this->deleteCustomFieldsForItem(Acl::CLIENT, $id);

                $this->eventDispatcher->notifyEvent('delete.client.selection',
                    new Event($this, EventMessage::factory()
                        ->addDescription(__u('Clients deleted')))
                );

                return $this->returnJsonResponse(JsonResponse::JSON_SUCCESS, __u('Clients deleted'));
            }
            $this->clientService->delete($id);

            $this->deleteCustomFieldsForItem(Acl::CLIENT, $id);

            $this->eventDispatcher->notifyEvent('delete.client',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Client deleted'))
                    ->addDetail(__u('Client'), $id))
            );

            return $this->returnJsonResponse(JsonResponse::JSON_SUCCESS, __u('Client deleted'));
        } catch (\Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent('exception', new Event($e));

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Saves create action
     */
    public function saveCreateAction()
    {
        try {
            $this->checkSecurityToken($this->previousSk, $this->request);

            if (!$this->acl->checkUserAccess(Acl::CLIENT_CREATE)) {
                return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('You don\'t have permission to do this operation'));
            }

            $form = new ClientForm($this->dic);
            $form->validate(Acl::CLIENT_CREATE);

            $itemData = $form->getItemData();

            $this->clientService->create($itemData);

            $this->eventDispatcher->notifyEvent('create.client',
                new Event($this,
                    EventMessage::factory()
                        ->addDescription(__u('Client added'))
                        ->addDetail(__u('Client'), $itemData->getName()))
            );

            return $this->returnJsonResponse(JsonResponse::JSON_SUCCESS, __u('Client added'));
        } catch (ValidationException $e) {
            return $this->returnJsonResponseException($e);
        } catch (\Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent('exception', new Event($e));

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Saves edit action
     *
     * @param $id
     *
     * @return bool
     */
    public function saveEditAction($id)
    {
        try {
            $this->checkSecurityToken($this->previousSk, $this->request);

            if (!$this->acl->checkUserAccess(Acl::CLIENT_EDIT)) {
                return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('You don\'t have permission to do this operation'));
            }

            $form = new ClientForm($this->dic, $id);
            $form->validate(Acl::CLIENT_EDIT);

            $this->clientService->update($form->getItemData());

            $this->eventDispatcher->notifyEvent('edit.client',
                new Event($this,
                    EventMessage::factory()
                        ->addDescription(__u('Client updated'))
                        ->addDetail(__u('Client'), $id))
            );

            return $this->returnJsonResponse(JsonResponse::JSON_SUCCESS, __u('Client updated'));
        } catch (ValidationException $e) {
            return $this->returnJsonResponseException($e);
        } catch (\Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent('exception', new Event($e));

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * View action
     *
     * @param $id
     *
     * @return bool
     */
    public function viewAction($id)
    {
        try {
            $this->checkSecurityToken($this->previousSk, $this->request);

            if (!$this->acl->checkUserAccess(Acl::CLIENT_VIEW)) {
                return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('You don\'t have permission to do this operation'));
            }

            $this->view->assign('header', __('View Client'));
            $this->view->assign('isView', true);

            $this->setViewData($id);

            $this->eventDispatcher->notifyEvent('show.client', new Event($this));

            return $this->returnJsonResponseData(['html' => $this->render()]);
        } catch (\Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent('exception', new Event($e));

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Initialize class
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \SP\Services\Auth\AuthException
     */
    protected function initialize()
    {
        $this->checkLoggedIn();

        $this->clientService = $this->dic->get(ClientService::class);
    }
}