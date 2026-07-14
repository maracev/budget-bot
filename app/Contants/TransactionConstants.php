<?php

namespace App\Contants;

class TransactionConstants
{
    public const OUTGO = 'gasto';

    public const CACHE_KEY_PREFIX = 'telegram_conversation_';

    public const CANCEL = 'cancel';


    public const STEP_AMOUNT = 'amount';
    public const STEP_CATEGORY_SELECTION = 'category_selection';
    public const STEP_SUBCATEGORY_SELECTION = 'subcategory_selection';
    public const STEP_NOTE = 'note';

    public const KEY_STEP = 'step';
    public const KEY_TYPE = 'type';
    public const KEY_AMOUNT = 'amount';
    public const KEY_CATEGORY = 'category';
    public const KEY_CATEGORY_ID = 'category_id';
    public const KEY_SUBCATEGORY = 'subcategory';

    public const TYPE_OUTGO = 'outgo';
    public const TYPE_INCOME = 'income';

    public const CMD_GASTO = 'gasto';
    public const CMD_INGRESO = 'ingreso';
    public const CMD_BALANCE = 'balance';
    public const CMD_FILTRO_BALANCE = 'filtro_balance';
    public const CMD_FILTRO_TX = 'filtro_tx';
    public const CMD_CIERRE = 'cierre';
    public const CMD_RESUMEN = 'resumen';
    public const CMD_TARJETA = 'tarjeta';
    public const CMD_TARJETA_BALANCE = 'tarjeta_balance';
    public const CMD_CATEGORIAS = 'categorias';

    public const PROMPT_AMOUNT = '¿Cuál es el monto?';
    public const PROMPT_CATEGORY = 'Seleccioná una categoría:';
    public const PROMPT_SUBCATEGORY = 'Seleccioná una subcategoría:';
    public const PROMPT_NOTE = 'Agregá una nota (opcional) o presioná "Saltar":';

    public const MSG_INVALID_AMOUNT = 'Monto inválido. Ingresá un número positivo.';
    public const MSG_CANCELLED = 'Operación cancelada.';
    public const MSG_INVALID_CATEGORY = 'Categoría inválida. Seleccioná una categoría:';
    public const MSG_CATEGORY_NOT_FOUND = 'Categoría no encontrada. Seleccioná una categoría:';
    public const MSG_INVALID_SUBCATEGORY = 'Subcategoría inválida. Seleccioná una subcategoría:';
    public const MSG_SUBCATEGORY_NOT_FOUND = 'Subcategoría no encontrada. Seleccioná una subcategoría:';

    public const SKIP_TEXT = '⏭ Saltar';
    public const SKIP_INDICATOR = '⏭';
    public const CHECK_PREFIX = '✅ ';
    public const CHECK_ALT = '✓';

    public const LOCALE = 'es';
}
