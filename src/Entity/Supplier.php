<?php

namespace App\Entity;

use App\Repository\SupplierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupplierRepository::class)]
class Supplier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, SupplyRequest>
     */
    #[ORM\OneToMany(targetEntity: SupplyRequest::class, mappedBy: 'supplier')]
    private Collection $supplyRequests;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isArchived = false;

    public function __construct()
    {
        $this->supplyRequests = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isArchived(): ?bool
    {
        return $this->isArchived;
    }

    public function setIsArchived(bool $isArchived): static{
        $this->isArchived = $isArchived;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, SupplyRequest>
     */
    public function getSupplyRequests(): Collection
    {
        return $this->supplyRequests;
    }

    public function addSupplyRequest(SupplyRequest $supplyRequest): static
    {
        if (!$this->supplyRequests->contains($supplyRequest)) {
            $this->supplyRequests->add($supplyRequest);
            $supplyRequest->setSupplier($this);
        }

        return $this;
    }

    public function removeSupplyRequest(SupplyRequest $supplyRequest): static
    {
        if ($this->supplyRequests->removeElement($supplyRequest)) {
            // set the owning side to null (unless already changed)
            if ($supplyRequest->getSupplier() === $this) {
                $supplyRequest->setSupplier(null);
            }
        }

        return $this;
    }
}
