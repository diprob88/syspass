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

namespace SP\Modules\Web\Controllers\Helpers\Account;

use SP\Core\Acl\AccountPermissionException;
use SP\Core\Acl\Acl;
use SP\Core\Acl\UnauthorizedPageException;
use SP\Core\Exceptions\SPException;
use SP\DataModel\AccountHistoryData;
use SP\DataModel\Dto\AccountAclDto;
use SP\Modules\Web\Controllers\Helpers\HelperBase;
use SP\Mvc\View\Components\SelectItemAdapter;
use SP\Services\Account\AccountAcl;
use SP\Services\Account\AccountAclService;
use SP\Services\Account\AccountHistoryService;
use SP\Services\Category\CategoryService;
use SP\Services\Client\ClientService;
use SP\Services\Crypt\MasterPassService;
use SP\Services\User\UpdatedMasterPassException;

/**
 * Class AccountHistoryHelper
 *
 * @package SP\Modules\Web\Controllers\Helpers
 */
final class AccountHistoryHelper extends HelperBase
{
    /**
     * @var Acl
     */
    protected $acl;
    /**
     * @var AccountHistoryService
     */
    protected $accountHistoryService;
    /**
     * @var int
     */
    protected $accountId;
    /**
     * @var int
     */
    protected $actionId;
    /**
     * @var int
     */
    protected $accountHistoryId;
    /**
     * @var AccountAcl
     */
    protected $accountAcl;

    /**
     * @param AccountHistoryData $accountHistoryData
     * @param int                $actionId
     *
     * @throws AccountPermissionException
     * @throws UnauthorizedPageException
     * @throws UpdatedMasterPassException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     * @throws \SP\Repositories\NoSuchItemException
     * @throws \SP\Services\ServiceException
     */
    public function setView(AccountHistoryData $accountHistoryData, $actionId)
    {
        $this->actionId = $actionId;
        $this->accountHistoryId = $accountHistoryData->getId();
        $this->accountId = $accountHistoryData->getAccountId();

        $this->checkActionAccess();
        $this->checkAccess($accountHistoryData);

        $this->view->assign('isView', true);
        $this->view->assign('accountIsHistory', true);
        $this->view->assign('accountData', $accountHistoryData);
        $this->view->assign('accountAcl', $this->accountAcl);
        $this->view->assign('actionId', $this->actionId);
        $this->view->assign('accountId', $this->accountId);

        $this->view->assign('historyData',
            SelectItemAdapter::factory($this->accountHistoryService->getHistoryForAccount($this->accountId))
                ->getItemsFromArraySelected([$this->accountHistoryId]));

        $this->view->assign('accountPassDate', date('Y-m-d H:i:s', $accountHistoryData->getPassDate()));
        $this->view->assign('accountPassDateChange', date('Y-m-d', $accountHistoryData->getPassDateChange() ?: 0));
        $this->view->assign('categories',
            SelectItemAdapter::factory(CategoryService::getItemsBasic())
                ->getItemsFromModelSelected([$accountHistoryData->getCategoryId()]));
        $this->view->assign('clients',
            SelectItemAdapter::factory(ClientService::getItemsBasic())
                ->getItemsFromModelSelected([$accountHistoryData->getClientId()]));
        $this->view->assign('isModified', strtotime($accountHistoryData->getDateEdit()) !== false);

        $accountActionsHelper = $this->dic->get(AccountActionsHelper::class);

        $accountActionsDto = new AccountActionsDto($this->accountId, $this->accountHistoryId, 0);

        $this->view->assign('accountActions',
            $accountActionsHelper->getActionsForAccount($this->accountAcl, $accountActionsDto));
        $this->view->assign('accountActionsMenu',
            $accountActionsHelper->getActionsGrouppedForAccount($this->accountAcl, $accountActionsDto));
    }

    /**
     * @throws UnauthorizedPageException
     * @throws UpdatedMasterPassException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \SP\Repositories\NoSuchItemException
     * @throws \SP\Services\ServiceException
     */
    protected function checkActionAccess()
    {
        if (!$this->acl->checkUserAccess($this->actionId)) {
            throw new UnauthorizedPageException(UnauthorizedPageException::INFO);
        }

        if (!$this->dic->get(MasterPassService::class)
            ->checkUserUpdateMPass($this->context->getUserData()->getLastUpdateMPass())
        ) {
            throw new UpdatedMasterPassException(UpdatedMasterPassException::INFO);
        }
    }

    /**
     * Comprobar si el usuario dispone de acceso al módulo
     *
     * @param AccountHistoryData $accountHistoryData
     *
     * @throws AccountPermissionException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    protected function checkAccess(AccountHistoryData $accountHistoryData)
    {
        $acccountAclDto = AccountAclDto::makeFromAccountHistory(
            $accountHistoryData,
            $this->accountHistoryService->getUsersByAccountId($this->accountId),
            $this->accountHistoryService->getUserGroupsByAccountId($this->accountId)
        );

        $this->accountAcl = $this->dic->get(AccountAclService::class)
            ->getAcl($this->actionId, $acccountAclDto, true);

        if ($this->accountAcl === null
            || $this->accountAcl->checkAccountAccess($this->actionId) === false
        ) {
            throw new AccountPermissionException(SPException::INFO);
        }
    }

    /**
     * Initialize class
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function initialize()
    {
        $this->acl = $this->dic->get(Acl::class);
        $this->accountHistoryService = $this->dic->get(AccountHistoryService::class);;
    }
}