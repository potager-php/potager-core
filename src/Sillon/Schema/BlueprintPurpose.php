<?php

namespace Potager\Sillon\Schema;

/**
 * Enum representing the purpose of a Blueprint (creating or altering a table).
 */
enum BlueprintPurpose: string
{
    case CREATE = 'create';
    case ALTER = 'alter';
}
