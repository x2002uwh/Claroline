<?php

namespace Claroline\CoreBundle\Entity\Workspace;

use \RuntimeException;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Claroline\CoreBundle\Entity\Role;
use JMS\SerializerBundle\Annotation\Type;

/**
 * @ORM\Entity(repositoryClass="Claroline\CoreBundle\Repository\WorkspaceRepository")
 * @ORM\Table(name="claro_workspace")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({
 *      "Claroline\CoreBundle\Entity\Workspace\SimpleWorkspace" = "Claroline\CoreBundle\Entity\Workspace\SimpleWorkspace",
 *      "Claroline\CoreBundle\Entity\Workspace\AggregatorWorkspace" = "Claroline\CoreBundle\Entity\Workspace\AggregatorWorkspace"
 * })
 */
abstract class AbstractWorkspace
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     */
    protected $name;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     */
    protected $code;

    /**
     * @ORM\Column(type="integer", length=255)
     */
    protected $type;

    /**
     * @ORM\Column(name="is_public", type="boolean")
     */
    protected $isPublic = true;

    /**
     * @ORM\OneToMany(
     *  targetEntity="Claroline\CoreBundle\Entity\Role",
     *  mappedBy="workspace",
     *  cascade={"persist"}
     * )
     */
    protected $roles;

    /**
     * @ORM\OneToMany(targetEntity="Claroline\CoreBundle\Entity\Resource\AbstractResource", mappedBy="workspace")
     */
    protected $resources;

    protected static $visitorPrefix = 'ROLE_WS_VISITOR';
    protected static $collaboratorPrefix = 'ROLE_WS_COLLABORATOR';
    protected static $managerPrefix = 'ROLE_WS_MANAGER';
    protected static $customPrefix = 'ROLE_WS_CUSTOM';

    const PERSONNAL = 0;
    const STANDARD = 1;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->tools = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = 0;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    abstract function setPublic($isPublic);

    public function isPublic()
    {
        return $this->isPublic;
    }

    /**
     * Creates the three workspace base roles (visitor, collaborator, manager)
     * and attaches them to the workspace instance. As the workspace role names
     * require the workspace to have a valid identifier, this method can't be used
     * on a workspace instance that has never been flushed.
     *
     * @throw RuntimeException if the workspace has no valid id
     */
    public function initBaseRoles()
    {
        $this->checkIdCondition();

        foreach ($this->roles as $storedRole) {
            if (self::isBaseRole($storedRole->getName())) {
                throw new RuntimeException('Base workspace roles are already set.');
            }
        }

        $visitorRole = $this->doAddBaseRole(self::$visitorPrefix, $this->createDefaultsResourcesRights(true, false, false, false, false, false));
        $collaboratorRole = $this->doAddBaseRole(self::$collaboratorPrefix, $this->createDefaultsResourcesRights(true, false, true, false, false, false), $visitorRole);
        $this->doAddBaseRole(self::$managerPrefix, $this->createDefaultsResourcesRights(true, true, true, true, true, true), $collaboratorRole);
    }

    public function getVisitorRole()
    {
        return $this->doGetBaseRole(self::$visitorPrefix);
    }

    public function getCollaboratorRole()
    {
        return $this->doGetBaseRole(self::$collaboratorPrefix);
    }

    public function getManagerRole()
    {
        return $this->doGetBaseRole(self::$managerPrefix);
    }

    /**
     * Returns the custom roles attached to the workspace instance. Note that
     * the returned collection is not the actual entity's role collection, so
     * using add/remove operations on it won't affect the entity's realtionships
     * (use addCustomRole and removeCustomRole to achieve that goal).
     *
     * @return ArrayCollection[WorkspaceRole]
     */
    public function getCustomRoles()
    {
        $customRoles = new ArrayCollection();

        foreach ($this->roles as $role) {
            if (self::isCustomRole($role->getName())) {
                $customRoles[] = $role;
            }
        }

        return $customRoles;
    }

    /**
     * Adds a custom role to the workspace's role collection. If the role doesn't have
     * a name or if the workspace doesn't have a valid identifier (i.e. hasn't been
     * flushed yet), an exception will be thrown.
     *
     * @param Role $role
     * @throw RuntimeException if the workspace has no id or if the role has no name
     */
    public function addCustomRole(Role $role)
    {
        $this->checkIdCondition();

        if ($this->roles->contains($role)) {
            return;
        }

        $workspace = $role->getWorkspace();

        if (!$workspace instanceof AbstractWorkspace) {
            $role->setWorkspace($this);
        } else {
            if ($workspace !== $this) {
                throw new RuntimeException(
                    'Workspace roles are bound to only one workspace and cannot '
                    . 'be associated with another workspace.'
                );
            }
        }

        $roleName = $role->getName();

        if (!is_string($roleName) || 0 == strlen($roleName)) {
            throw new RuntimeException('Workspace role must have a valid name.');
        }

        $newRoleName = self::$customPrefix . "_{$this->getId()}_{$roleName}";
        $role->setName($newRoleName);
        $this->roles->add($role);
    }

    public function removeCustomRole(Role $role)
    {
        if (0 === strpos($role->getName(), self::$customPrefix . "_{$this->getId()}_")) {
            $this->roles->removeElement($role);
        }
    }

    public static function isBaseRole($roleName)
    {
        if (0 === strpos($roleName, self::$visitorPrefix)
            || 0 === strpos($roleName, self::$collaboratorPrefix)
            || 0 === strpos($roleName, self::$managerPrefix)) {
            return true;
        }

        return false;
    }

    public static function isCustomRole($roleName)
    {
        if (0 === strpos($roleName, self::$customPrefix)) {
            return true;
        }

        return false;
    }

    private function checkIdCondition()
    {
        if (null === $this->id) {
            throw new RuntimeException(
                'Workspace must be flushed and have a valid id '
                . 'before associating roles to it.'
            );
        }
    }

    private function doAddBaseRole($prefix, $rsw, $parent = null)
    {
        $baseRole = new Role();
        $baseRole->setWorkspace($this);
        $baseRole->setName("{$prefix}_{$this->getId()}");
        $baseRole->setParent($parent);
        $baseRole->setRoleType(Role::WS_ROLE);
        $baseRole->addResourceRights($rsw);
        $rsw->setRole($baseRole);
        $this->roles->add($baseRole);

        return $baseRole;
    }

    private function doGetBaseRole($prefix)
    {
        foreach ($this->roles as $role) {
            if (0 === strpos($role->getName(), $prefix)) {
                return $role;
            }
        }
    }

    public function getWorkspaceRoles()
    {
        return $this->roles;
    }

    public function getResources()
    {
        return $this->resources;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setCode($code)
    {
        $this->code = $code;
    }

    public function getCode()
    {
        return $this->code;
    }

    /**
     * Creates a ResourceRights entity (will be used as the default one)
     * @param boolean $canSee
     * @param boolean $canDelete
     * @param boolean $canOpen
     * @param boolean $canEdit
     * @param boolean $canCopy
     * @param boolean $canShare
     * @param boolean $canCreate
     *
     * @return ResourceRights
     */
    private function createDefaultsResourcesRights($canSee, $canDelete, $canOpen, $canEdit, $canCopy, $canCreate)
    {
        $resourceRight = new ResourceRights();
        $resourceRight->setCanCopy($canCopy);
        $resourceRight->setCanDelete($canDelete);
        $resourceRight->setCanEdit($canEdit);
        $resourceRight->setCanOpen($canOpen);
        $resourceRight->setCanSee($canSee);
        $resourceRight->setCanCreate($canCreate);

        return $resourceRight;
    }
}