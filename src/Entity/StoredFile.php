<?php

namespace App\Entity;

use App\Repository\StoredFileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StoredFileRepository::class)]
class StoredFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\OneToOne(mappedBy: 'image', cascade: ['persist', 'remove'])]
    private ?CreatePostStoredJob $createPostJob = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getCreatePostJob(): ?CreatePostStoredJob
    {
        return $this->createPostJob;
    }

    public function setCreatePostJob(?CreatePostStoredJob $createPostJob): static
    {
        // unset the owning side of the relation if necessary
        if ($createPostJob === null && $this->createPostJob !== null) {
            $this->createPostJob->setImage(null);
        }

        // set the owning side of the relation if necessary
        if ($createPostJob !== null && $createPostJob->getImage() !== $this) {
            $createPostJob->setImage($this);
        }

        $this->createPostJob = $createPostJob;

        return $this;
    }
}
