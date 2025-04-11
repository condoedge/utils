<?php

namespace Condoedge\Utils\Models;

enum ChangeTypeEnum: int
{
    case CREATE = 1;
    case UPDATE = 2;    
}