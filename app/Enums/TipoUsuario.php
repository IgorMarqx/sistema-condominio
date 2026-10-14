<?php

namespace App\Enums;

enum TipoUsuario: string
{
    case Admin = 'admin';
    case Unidade = 'unidade';
    case Conselho = 'conselho';
}
