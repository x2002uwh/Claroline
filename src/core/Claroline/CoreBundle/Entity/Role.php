<?php

namespace Claroline\CoreBundle\Entity;

use \RuntimeException;
use Symfony\Component\Security\Core\Role\RoleInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints as DoctrineAssert;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\Common\Collections\ArrayCollection;
use Claroline\CoreBundle\Library\Security\PlatformRoles;
use Claroline\CoreBundle\Entity\Resource\ResourceRights;

/**
 * @ORM\Entity(repositoryClass="Claroline\CoreBundle\Repository\RoleRepository")
 * @ORM\Table(name="claro_role")
 * @ORM\HasLifecycleCallbacks
 * @Gedmo\Tree(type="nested")
 * @DoctrineAssert\UniqueEntity("name")
 */
class Role implements RoleInterface
{
    const BASE_ROLE = 1;
    const WS_ROLE = 2;
    const CUSTOM_ROLE = 3;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="name", type="string", length=50)
     * @Assert\NotBlank()
     */
    protected $name;

    /**
     * @ORM\Column(name="translation_key", type="string", length=255)
     */
    protected $translationKey;

    /**
     * @ORM\Column(name="is_read_only", type="boolean")
     */
    protected $isReadOnly = false;

    /**
     * @Gedmo\TreeLeft
     * @ORM\Column(name="lft", type="integer")
     */
    protected $lft;

    /**
     * @Gedmo\TreeLevel
     * @ORM\Column(name="lvl", type="integer")
     */
    protected $lvl;

    /**
     * @Gedmo\TreeRight
     * @ORM\Column(name="rgt", type="integer")
     */
    protected $rgt;

    /**
     * @Gedmo\TreeRoot
     * @ORM\Column(name="root", type="integer", nullable=true)
     */
    protected $root;

    /**
     * @Gedmo\TreeParent
     * @ORM\ManyToOne(
     *     targetEntity="Claroline\CoreBundle\Entity\Role",
     *     inversedBy="children"
     * )
     * @ORM\JoinColumn(
     *     name="parent_id",
     *     referencedColumnName="id",
     *     onDelete="SET NULL"
     * )
     */
    protected $parent;

    /**
     * @ORM\OneToMany(
     *     targetEntity="Claroline\CoreBundle\Entity\Role",
     *     mappedBy="parent"
     * )
     * @ORM\OrderBy({"lft" = "ASC"})
     */
    protected $children;

    /**
     * @ORM\ManyToMany(
     *     targetEntity="Claroline\CoreBundle\Entity\User",
     *     mappedBy="roles"
     * )
     * @ORM\JoinTable(
     *     name="claro_user_role",
     *     joinColumns={
     *         @ORM\JoinColumn(name="role_id", referencedColumnName="id")
     *     },
     *     inverseJoinColumns={
     *         @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     *     }
     * )
     */
    protected $users;

    /**
     * @ORM\ManyToMany(
     *     targetEntity="Claroline\CoreBundle\Entity\Group",
     *     mappedBy="roles"
     * )
     * @ORM\JoinTable(
     *     name="claro_group_role",
     *     joinColumns={
     *         @ORM\JoinColumn(name="role_id", referencedColumnName="id")
     *     },
     *     inverseJoinColumns={
     *         @ORM\JoinColumn(name="group_id", referencedColumnName="id")
     *     }
     * )
     */
    protected $groups;

    /**
     * @ORM\Column(name="role_type", type="integer")
     */
    protected $roleType;

    /**
     * @ORM\OneToMany(
     *     targetEntity="Claroline\CoreBundle\Entity\Resource\ResourceRights",
     *     mappedBy="role"
     * )
     */
    protected $resourceRights;

    /**
     * @ORM\OneToMany(
     *     targetEntity="Claroline\CoreBundle\Entity\Rights\WorkspaceRights",
     *     mappedBy="role"
     * )
     */
    protected $workspaceRights;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->resourceContext = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the role name. The name must be prefixed by 'ROLE_'. Note that
     * platform-wide roles (as listed in Claroline/CoreBundle/Security/PlatformRoles)
     * cannot be modified by this setter.
     *
     * @param string $name
     * @throw RuntimeException if the name isn't prefixed by 'ROLE_' or if the role is platform-wide
     */
    public function setName($name)
    {
        if (0 !== strpos($name, 'ROLE_')) {
            throw new RuntimeException('Role names must start with "ROLE_"');
        }

        if (PlatformRoles::contains($this->name)) {
            throw new RuntimeException('Platform roles cannot be modified');
        }

        if (PlatformRoles::contains($name)) {
            $this->isReadOnly = true;
        }

        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setTranslationKey($key)
    {
        $this->translationKey = $key;
    }

    public function getTranslationKey()
    {
        return $this->translationKey;
    }

    public function isReadOnly()
    {
        return $this->isReadOnly;
    }

    /**
     * Alias of getName().
     *
     * @return string The role name.
     */
    public function getRole()
    {
        return $this->getName();
    }

    public function setParent(Role $role = null)
    {
        $this->parent = $role;
    }

    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @ORM\PreRemove
     */
    public function preRemove()
    {
        if (PlatformRoles::contains($this->name)) {
            throw new RuntimeException('Platform roles cannot be deleted');
        }
    }

    protected function setReadOnly($value)
    {
        $this->isReadOnly = $value;
    }

    public function getUsers()
    {
        return $this->users;
    }

    public function addUser($user)
    {
        $this->users->add($user);

        if ($user->hasRole($this)) {
            $user->addRole($this);
        }
    }

    public function setRoleType($roleType)
    {
        $this->roleType = $roleType;
    }

    public function getRoleType()
    {
        return $this->roleType;
    }

    public function addResourceRights(ResourceRights $rc)
    {
        $this->resourceRights->add($rc);
    }

    public function getResourceRights()
    {
        return $this->resourceRights;
    }
}