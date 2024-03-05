<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(groups: ['Product:read', 'product:edit', 'product:write', 'product:delete'])]
    #[ORM\Column(length: 120, unique: true)]
    private ?string $title = null;

    #[Groups(groups: ['Product:read', 'product:edit', 'product:write', 'product:delete'])]
    #[ORM\Column]
    private ?float $price = null;

    #[Groups(groups: ['Product:read', 'product:edit', 'product:write', 'product:delete'])]
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?self $parent = null;

    #[Groups(groups: ['Product:read', 'product:edit', 'product:write', 'product:delete'])]
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', orphanRemoval: true)]
    private Collection $products;

    #[Groups(groups: ['Product:read', 'product:edit', 'product:write', 'product:delete'])]
    #[ORM\ManyToOne(inversedBy: 'products')]
    private ?Category $category = null;

    public function __construct()
    {
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(self $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setParent($this);
        }

        return $this;
    }

    public function removeProduct(self $product): static
    {
        if ($this->products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getParent() === $this) {
                $product->setParent(null);
            }
        }

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }
}
