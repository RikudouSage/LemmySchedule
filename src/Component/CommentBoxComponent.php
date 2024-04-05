<?php

namespace App\Component;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class CommentBoxComponent
{
    public string $name;

    public ?string $inputId = null;

    public ?string $comment = null;
}
