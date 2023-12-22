<?php
/**
 * Class represents records from table aff_commission_rule
 * {autogenerated}
 * @property int $rule_id
 * @property string $comment
 * @property int $sort_order
 * @property mixed $conditions
 * @property double $free_signup_c
 * @property string $free_signup_t
 * @property double $first_payment_c
 * @property string $first_payment_t
 * @property double $recurring_c
 * @property string $recurring_t
 * @property int $type
 * @property int $tier
 * @property float $multi
 * @property bool $is_disabled
 * @see Am_Table
 */
class AffCommissionRule extends Am_Record
{
    const TYPE_MULTI    = 'multi';
    const TYPE_CUSTOM   = 'custom';
    const TYPE_GLOBAL   = 'global';

    const COND_AFF_FIRST_TIME_PAYMENT = 'aff_first_time_payment';
    const COND_AFF_SALES_COUNT = 'aff_sales_count';
    const COND_AFF_ITEMS_COUNT = 'aff_items_count';
    const COND_AFF_SALES_AMOUNT = 'aff_sales_amount';
    const COND_AFF_HAS_COMM_WITH_USER = 'aff_has_comm_with_user';
    const COND_AFF_GROUP_ID = 'aff_group_id';
    const COND_AFF_PRODUCT_ID = 'aff_product_id';
    const COND_AFF_PRODUCT_CATEGORY_ID = 'aff_product_category_id';
    const COND_AFF_NOT_PRODUCT_ID = 'aff_not_product_id';
    const COND_AFF_NOT_PRODUCT_CATEGORY_ID = 'aff_not_product_category_id';
    const COND_PRODUCT_ID  = 'product_id';
    const COND_PRODUCT_CATEGORY_ID = 'product_category_id';
    const COND_BILLING_PLAN_ID = 'billing_plan_id';
    const COND_NOT_PRODUCT_ID = 'not_product_id';
    const COND_NOT_PRODUCT_CATEGORY_ID = 'not_product_category_id';
    const COND_NOT_BILLING_PLAN_ID = 'not_billing_plan_id';
    const COND_COUPON = 'coupon';
    const COND_PAYSYS_ID = 'paysys_id';
    const COND_NOT_PAYSYS_ID = 'not_paysys_id';
    const COND_FIRST_TIME = 'first_time';
    const COND_FIRST_TIME_PAYMENT = 'first_time_payment';
    const COND_UPGRADE = 'upgrade';
    const COND_INVOICE_PAYMENT_NUM = 'invoice_payment_num';

    static function getTypes()
    {
        return [
            self::TYPE_CUSTOM   => ___("Custom Commission"),
            self::TYPE_MULTI    => ___("Multiplier"),
        ];
    }

    function getTypeTitle()
    {
        $level = $this->tier + 1;
        $types = self::getTypes();

        return $this->type == self::TYPE_GLOBAL ? ($this->tier > 0 ? ___('%d-Tier Affiliates Commission
', $level) : ___('Global Commission')) : $types[$this->type];
    }

    function isGlobal()
    {
        if (empty($this->type)) return false;
        return self::isGlobalType($this->type);
    }

    static function isGlobalType($type)
    {
        return $type == self::TYPE_GLOBAL;
    }

    function match(Invoice $invoice, InvoiceItem $item, User $aff, $paymentNumber = 0, $tier = 0, $paymentDate = 'now')
    {
        if ($this->type == self::TYPE_GLOBAL)
            return $tier == $this->tier;

        if ($tier != 0) return false; // no custom rules for 2-tier

        // check conditions
        foreach ($this->getConditions() as $conditionType => $vars)
        {
            switch ($conditionType)
            {
                case self::COND_AFF_SALES_COUNT:;
                case self::COND_AFF_ITEMS_COUNT:;
                case self::COND_AFF_SALES_AMOUNT:;
                    if (empty($vars['count']) || (empty($vars['period_type']) && empty($vars['days']))) return false;
                    $period_type = $vars['period_type'] ?? 'recent_days';
                    $e = sqlDate($paymentDate);
                    switch ($period_type) {
                        case 'recent_days' :
                            $b = sqlDate($e . '-' . $vars['days'] . ' days');
                            break;
                        case 'month':
                            $b = date('Y-m-01', strtotime($e));
                            break;
                        case 'all':
                            $b = "1970-01-01";
                            break;
                    }

                    $stats = $this->getDi()->affCommissionTable->getAffStats($aff->pk(), $b, $e);
                    switch ($conditionType)
                    {
                        case self::COND_AFF_ITEMS_COUNT: $key = 'items_count'; break;
                        case self::COND_AFF_SALES_COUNT: $key = 'count'; break;
                        default: $key = 'amount';
                    }
                    if ($stats[$key] < $vars['count'])
                        return false;
                    break;
                case self::COND_AFF_HAS_COMM_WITH_USER:
                    $has_comm = $this->_db->selectCell(<<<CUT
                        SELECT COUNT(*)
                            FROM ?_aff_commission c
                            LEFT JOIN ?_invoice i USING (invoice_id)
                            WHERE c.aff_id = ? AND i.user_id = ?;
CUT
                        , $aff->pk(), $invoice->getUser()->pk());
                    switch($vars['type']) {
                        case 'yes' :
                            if (!$has_comm) return false;
                            break;
                        case 'no' :
                            if ($has_comm) return false;
                            break;
                    }
                    break;
                case self::COND_AFF_GROUP_ID:
                    if (!array_intersect($aff->getGroups(), (array)$vars))
                        return false;
                    break;
                case self::COND_PRODUCT_ID:
                    if (($item->item_type != 'product') || !in_array($item->item_id, (array)$vars))
                        return false;
                    break;
                case self::COND_PRODUCT_CATEGORY_ID:
                    if ($item->item_type != 'product') return false;
                    $pr = $item->tryLoadProduct();
                    if (!$pr) return false;
                    if (!array_intersect($pr->getCategories(), (array)$vars))
                        return false;
                    break;
                case self::COND_BILLING_PLAN_ID:
                    if (!in_array($item->billing_plan_id, (array)$vars))
                        return false;
                    break;
                case self::COND_NOT_PRODUCT_ID:
                    if (($item->item_type == 'product') && in_array($item->item_id, (array)$vars))
                        return false;
                    break;
                case self::COND_NOT_PRODUCT_CATEGORY_ID:
                    if ($item->item_type == 'product' &&
                        ($pr = $item->tryLoadProduct()) &&
                        array_intersect($pr->getCategories(), (array)$vars)) {
                            return false;
                    }
                    break;
                case self::COND_NOT_BILLING_PLAN_ID:
                    if (in_array($item->billing_plan_id, (array)$vars))
                        return false;
                    break;
                case self::COND_AFF_FIRST_TIME_PAYMENT :
                    $cnt = $this->getDi()->invoiceTable->countBy([
                        'aff_id' => $aff->pk(),
                        'status' => [Invoice::PAID, Invoice::RECURRING_ACTIVE, Invoice::RECURRING_CANCELLED, Invoice::RECURRING_FAILED, Invoice::RECURRING_FINISHED]
                    ]);
                    if ($vars && ($cnt!=1))
                        return false;
                    if (!$vars && ($cnt==1))
                        return false;
                    break;
                case self::COND_AFF_PRODUCT_ID:
                    if (!array_intersect($aff->getActiveProductIds(), (array)$vars))
                        return false;
                    break;
                case self::COND_AFF_PRODUCT_CATEGORY_ID:
                    $p_c = $this->getDi()->productCategoryTable->getCategoryProducts();
                    $product_ids = [];
                    foreach ((array)$vars as $cid) {
                        $product_ids = array_merge($product_ids, $p_c[$cid]);
                    }
                    if (!array_intersect($aff->getActiveProductIds(), $product_ids))
                        return false;
                    break;
                case self::COND_AFF_NOT_PRODUCT_ID:
                    if (array_intersect($aff->getActiveProductIds(), (array)$vars))
                        return false;
                    break;
                case self::COND_AFF_NOT_PRODUCT_CATEGORY_ID:
                    $p_c = $this->getDi()->productCategoryTable->getCategoryProducts();
                    $product_ids = [];
                    foreach ((array)$vars as $cid) {
                        $product_ids = array_merge($product_ids, $p_c[$cid]);
                    }
                    if (array_intersect($aff->getActiveProductIds(), $product_ids))
                        return false;
                    break;
                case self::COND_COUPON:
                    try
                    {
                        $coupon = $invoice->getCoupon();
                    }
                    catch(Am_Exception $ex)
                    {
                        $this->getDi()->logger->error("Could not load coupon", ["exception" => $ex]);
                        break;
                    }
                    switch ($vars['type']) {
                        case 'any' :
                            $coupon_cond_match = (bool)$coupon;
                            break;
                        case 'coupon':
                            $coupon_cond_match = $coupon && ($vars['code'] == $coupon->code);
                            break;
                        case 'batch' :
                            $coupon_cond_match = $coupon && ($vars['batch_id'] == $coupon->batch_id);
                            break;
                    }
                    if ($vars['used'] ? !$coupon_cond_match : $coupon_cond_match)
                        return false;
                    break;
                case self::COND_PAYSYS_ID :
                    if (!in_array($invoice->paysys_id, (array)$vars))
                        return false;
                    break;
                case self::COND_NOT_PAYSYS_ID :
                    if (in_array($invoice->paysys_id, (array)$vars))
                        return false;
                    break;
                case self::COND_FIRST_TIME :
                    if ($item->item_type == 'product' && $this->getDi()->accessTable->countBy([
                            'user_id' => $invoice->getUser()->pk(),
                            'product_id' => $item->item_id,
                            ['begin_date', '<=', sqlDate($paymentDate)],
                            ['invoice_id', '<>', $invoice->pk()]
                        ]) > 0)
                        return false;
                    break;
                case self::COND_FIRST_TIME_PAYMENT :
                    $cnt = $this->getDi()->invoicePaymentTable->countBy([
                            'user_id' => $invoice->getUser()->pk()
                    ]);
                    if ($vars && ($cnt!=1))
                        return false;
                    if (!$vars && ($cnt==1))
                        return false;
                    break;
                case self::COND_UPGRADE :
                    if (!$invoice->data()->get(Invoice::UPGRADE_INVOICE_ID))
                        return false;
                    break;
                case self::COND_INVOICE_PAYMENT_NUM :
                    switch($vars['op']) {
                        case '=':
                            if ($paymentNumber != $vars['val']) return false;
                            break;
                        case '>':
                            if ($paymentNumber < $vars['val']) return false;
                            break;
                        case '<':
                            if ($paymentNumber > $vars['val']) return false;
                            break;
                    }
                    break;
                default:
                    return false;
            }
        }
        return true;
    }

    function getConditions()
    {
        return (array)json_decode($this->conditions, true);
    }

    function setConditions(array $conditions)
    {
        $this->conditions = json_encode($conditions);
        return $this;
    }

    function render($pad = "")
    {
        $condition = $this->renderConditions();
        if ($condition) $condition = $pad . "  conditions: " . $condition. "\n";
        return
            sprintf("%s%s (%s)\n", $pad, $this->comment, $this->renderCommission()).
            $condition;
    }

    protected function formatPeriod($period_type, $days)
    {
        switch ($period_type) {
            case 'recent_days' :
                return sprintf('within recent %d days', $days);
            case 'month':
                return 'within current month';
            case 'all':
                return "all time";
        }
    }

    function renderConditions()
    {
        $ret = [];
        foreach ($this->getConditions() as $conditionType => $vars)
        {
            switch ($conditionType)
            {
                case self::COND_AFF_ITEMS_COUNT:
                    $ret[] = ___(
                        "Affiliate generated %d item sales %s",
                        $vars['count'],
                        $this->formatPeriod($vars['period_type'] ?? 'recent_days', $vars['days'])
                    );
                    break;
                case self::COND_AFF_SALES_COUNT:
                    $ret[] = ___(
                        "Affiliate generated %d sales %s",
                        $vars['count'],
                        $this->formatPeriod($vars['period_type'] ?? 'recent_days', $vars['days'])
                    );
                    break;
                case self::COND_AFF_SALES_AMOUNT:
                    $ret[] = ___(
                        "Affiliate generated %d%s in total sales amount %s",
                        $vars['count'],
                        Am_Currency::getDefault(),
                        $this->formatPeriod($vars['period_type'] ?? 'recent_days', $vars['days'])
                    );
                    break;
                case self::COND_AFF_HAS_COMM_WITH_USER:
                    $ret[] = $vars['type'] == 'yes' ?
                        ___("Affiliate already has commission with this user") :
                        ___("Affiliate has not commission with this user yet");
                    break;
                case self::COND_AFF_GROUP_ID:
                    $v = [];
                    foreach ($this->getDi()->userGroupTable->loadIds((array)$vars) as $group)
                        $v[] = $group->title;
                    $ret[] = ___("Affiliate Group IN (%s)", implode(", ", $v));
                    break;
                case self::COND_PRODUCT_ID:
                    $v = [];
                    foreach ($this->getDi()->productTable->loadIds((array)$vars) as $product)
                        $v[] = $product->title;
                    $ret[] = ___("Products IN (%s)", implode(", ", $v));
                    break;
                case self::COND_PRODUCT_CATEGORY_ID:
                    $v = [];
                    foreach ($this->getDi()->productCategoryTable->loadIds((array)$vars) as $product)
                        $v[] = $product->title;
                    $ret[] = ___("Product Category IN (%s)", implode(", ", $v));
                    break;
                case self::COND_BILLING_PLAN_ID:
                    $v = [];
                    foreach ($this->getDi()->billingPlanTable->loadIds((array)$vars) as $plan) {
                        $product = $plan->getProduct();
                        $v[] = "{$product->title} ({$plan->getTerms()})";
                    }
                    $ret[] = ___("Billing Plan IN (%s)", implode(", ", $v));
                    break;
                case self::COND_NOT_PRODUCT_ID:
                    $v = [];
                    foreach ($this->getDi()->productTable->loadIds((array)$vars) as $product)
                        $v[] = $product->title;
                    $ret[] = ___("Products NOT IN (%s)", implode(", ", $v));
                    break;
                case self::COND_NOT_PRODUCT_CATEGORY_ID:
                    $v = [];
                    foreach ($this->getDi()->productCategoryTable->loadIds((array)$vars) as $product)
                        $v[] = $product->title;
                    $ret[] = ___("Product Category NOT IN (%s)", implode(", ", $v));
                    break;
                case self::COND_NOT_BILLING_PLAN_ID:
                    $v = [];
                    foreach ($this->getDi()->billingPlanTable->loadIds((array)$vars) as $plan) {
                        $product = $plan->getProduct();
                        $v[] = "{$product->title} ({$plan->getTerms()})";
                    }
                    $ret[] = ___("Billing Plan NOT IN (%s)", implode(", ", $v));
                    break;
                case self::COND_AFF_PRODUCT_ID:
                    $v = [];
                    foreach ($this->getDi()->productTable->loadIds((array)$vars) as $product)
                        $v[] = $product->title;
                    $ret[] = ___("Affiliate has Products IN (%s)", implode(", ", $v));
                    break;
                case self::COND_AFF_PRODUCT_CATEGORY_ID:
                    $v = [];
                    foreach ($this->getDi()->productCategoryTable->loadIds((array)$vars) as $product)
                        $v[] = $product->title;
                    $ret[] = ___("Affiliate has Products from Category IN (%s)", implode(", ", $v));
                    break;
                case self::COND_AFF_NOT_PRODUCT_ID:
                    $v = [];
                    foreach ($this->getDi()->productTable->loadIds((array)$vars) as $product)
                        $v[] = $product->title;
                    $ret[] = ___("Affiliate has not Products IN (%s)", implode(", ", $v));
                    break;
                case self::COND_AFF_NOT_PRODUCT_CATEGORY_ID:
                    $v = [];
                    foreach ($this->getDi()->productCategoryTable->loadIds((array)$vars) as $product)
                        $v[] = $product->title;
                    $ret[] = ___("Affiliate has not Products from Category IN (%s)", implode(", ", $v));
                    break;
                case self::COND_COUPON:
                    $txt = '';
                    switch ($vars['type']) {
                        case 'any' :
                            $txt = $vars['used']  ?
                                ___('Used Any Coupon') :
                                ___("Did't Use Any Coupon");
                            break;
                        case 'coupon' :
                            $txt = $vars['used'] ?
                                ___('Used Coupon with Code: %s', $vars['code']) :
                                ___("Did't Use Coupon with Code: %s", $vars['code']);
                            break;
                        case 'batch' :
                            $options = $this->getDi()->couponBatchTable->getOptions(false);
                            $txt = $vars['used'] ?
                                ___('Used Coupon from Batch: %s', $options[$vars['batch_id']]) :
                                ___("Did't Use Coupon from Batch: %s", $options[$vars['batch_id']]);
                            break;
                    }
                    $ret[] = $txt;
                    break;
                case self::COND_PAYSYS_ID:
                    $ret[] = ___("Paysystem IN (%s)", implode(', ', (array)$vars));
                    break;
                case self::COND_NOT_PAYSYS_ID:
                    $ret[] = ___("Paysystem NOT IN (%s)", implode(', ', (array)$vars));
                    break;
                case self::COND_FIRST_TIME:
                    $ret[] = ___('First Time Purchase(or get for free) the Product');
                    break;
                case self::COND_FIRST_TIME_PAYMENT:
                    $ret[] = ___('First Time Purchase: %s', $vars ? ___('Yes') : ___('No'));
                    break;
                case self::COND_AFF_FIRST_TIME_PAYMENT:
                    $ret[] = ___('First Time Affiliate Sale: %s', $vars ? ___('Yes') : ___('No'));
                    break;
                case self::COND_UPGRADE:
                    $ret[] = ___('Upgrade Invoice');
                    break;
                case self::COND_INVOICE_PAYMENT_NUM:
                    $ret[] = ___('Number of payment in invoice %s %d', $vars['op'], $vars['val']);
                    break;
                default:
                    return false;
            }
        }
        return implode(" AND ", $ret);
    }

    /**
     * Return human-readable representation of commission
     */
    function renderCommission()
    {
        $text = "";
        if ($this->type == AffCommissionRule::TYPE_MULTI)
            return ___('commission found by next rules') . ' &times; ' . $this->multi;
        if ($this->type == AffCommissionRule::TYPE_GLOBAL && $this->tier>0)
            return $this->first_payment_c . '%';
        foreach (['first_payment' => ___('First Payment'), 'recurring' => ___('Second and Subsequent Payments'), 'free_signup' => ___('Free Signup')] as $fieldName => $label)
        {
            if ($this->get($fieldName . '_c') <= 0) continue;
            $v = $this->get($fieldName . '_t') == '$' ?
                Am_Currency::render($this->get($fieldName . '_c')) :
                $this->get($fieldName . '_c') . $this->get($fieldName . '_t');
            $text .= $label . ": " . $v . ", ";
        }
        return trim($text, ', ');
    }
}

class AffCommissionRuleTable extends Am_Table
{
    protected $_key = 'rule_id';
    protected $_table = '?_aff_commission_rule';
    protected $_recordClass = 'AffCommissionRule';

    protected $rulesCache = [];

    /**
     * @return Am_Query with correct order set
     */
    public function createQuery()
    {
        $q = new Am_Query($this);
        $q->setOrderRaw("IF(t.type='multi', 1, t.type+0), sort_order");
        return $q;
    }

    public function _resetCache()
    {
        $this->rulesCache = [];
    }

    /**
     * @param Invoice $invoice
     * @param int $paymentNumber number of payment for given invoice, staring from 0
     * @param int $tier Affiliate level - starting from 0
     * @return array<AffCommissionRule> matching the invoice
     */
    public function findRules(Invoice $invoice, InvoiceItem $item, User $aff, $paymentNumber = 0, $tier = 0, $paymentDate = 'now')
    {
        if (!$this->rulesCache)
        {
            $this->rulesCache = $this->createQuery()->selectPageRecords(0, 99999);
        }
        $ret = [];
        foreach ($this->rulesCache as $rule)
        {
            /* @var $rule AffCommissionRule */
            if ($rule->match($invoice, $item, $aff, $paymentNumber, $tier, $paymentDate))
            {
                $ret[] = $rule;
                if($rule->type == AffCommissionRule::TYPE_GLOBAL && $rule->tier > 0) $rule->first_payment_t = '%';
                if ($rule->type != AffCommissionRule::TYPE_MULTI)
                    break; // last rule
            }
        }
        return $ret;
    }

    public function calculate(Invoice $invoice, InvoiceItem $item, User $aff, $paymentNumber = 0, $tier = 0, $paymentAmount = 0.0, $paymentDate = 'now', & $matchedRules = []
    )
    {
        // take aff.commission_days in account for 1-tier only
        if (
            $tier == 0 &&
            ($commissionDays = $this->getDi()->config->get('aff.commission_days')) &&
            ($invoice->getUser()->aff_id == $invoice->aff_id)
        )
        {
            $signupDays = $this->getDi()->time - strtotime($invoice->getUser()->aff_added ? $invoice->getUser()->aff_added : $invoice->getUser()->added);
            $signupDays = intval($signupDays / (3600*24)); // to days
            if ($commissionDays < $signupDays)
                return; // no commission for this case, affiliate<->user relation is expired
        }

        $multi = 1.0;
        $isFirst = $paymentNumber<=1;
        $prefix = $isFirst && (float)$item->first_total ? 'first' : 'second';

        if ($tier == 0) // for tier 0 get amount paid for given item
        {
            if ($invoice->get("{$prefix}_total") == 0) {
                $paidForItem = 0; // avoid division by zero
            } else {
                $item_total = $item->get("{$prefix}_total");
                $invoice_total = $invoice->get("{$prefix}_total");

                if (!$this->getDi()->config->get('aff.commission_include_tax', false)) {
                    $item_total -= $item->get("{$prefix}_tax");
                    $invoice_total -= $invoice->get("{$prefix}_tax");
                }

                if (!$this->getDi()->config->get('aff.commission_include_shipping', false)) {
                    $item_total -= $item->get("{$prefix}_shipping");
                    $invoice_total -= $invoice->get("{$prefix}_shipping");
                }

                $paidForItem = $paymentAmount * $item_total / $invoice_total;
            }
        } else { // for higher tier just take amount paid to previous tier
            $paidForItem = $paymentAmount;
        }
        $paidForItem = $tier ? $paidForItem : $paidForItem/$invoice->base_currency_multi;

        $comm = 0;
        foreach ($this->findRules($invoice, $item, $aff, $paymentNumber, $tier, $paymentDate) as $rule)
        {
            array_push($matchedRules, $rule);
            // Second tier commission have to be calculated as percent from First tier commission.
            if ($tier > 0) {
                $comm = moneyRound($rule->first_payment_c * $paidForItem / 100);
                break;
            }

            if ($rule->type == AffCommissionRule::TYPE_MULTI) {
                $multi *= $rule->multi;
            } else {
                if ($paidForItem == 0)
                {
                    // free signup?
                    if (($paymentNumber == 0) && $rule->free_signup_c) {
                        $comm = moneyRound($multi * $rule->free_signup_c);
                        break;
                    }
                } elseif ($isFirst) {
                    // first payment
                    if ($rule->first_payment_t == '%') {
                        $comm = moneyRound($multi * $rule->first_payment_c * $paidForItem / 100);
                        break;
                    } else {
                        $comm = moneyRound($multi * $item->qty * $rule->first_payment_c);
                        break;
                    }
                } else {
                    // recurring payment
                    if ($rule->recurring_t == '%') {
                        $comm = moneyRound($multi * $rule->recurring_c * $paidForItem / 100);
                        break;
                    } else {
                        $comm = moneyRound($multi * $item->qty * $rule->recurring_c);
                        break;
                    }
                }
            }
        }
        return $this->getDi()->hook->filter($comm, Bootstrap_Aff::AFF_COMMISSION_CALCULATE, [
            'invoice' => $invoice,
            'invoiceItem' => $item,
            'aff' => $aff,
            'paymentNumber' => $paymentNumber,
            'tier' => $tier,
            'paymentAmount' => $paymentAmount,
            'paymentDate' => $paymentDate
        ]);
    }

    public function getMaxTier()
    {
        return $this->getDi()->db->selectCell("SELECT MAX(tier) FROM ?_aff_commission_rule");
    }

    /**
     * Process invoice and insert necessary commissions for it
     *
     * External code should guarantee that this method with $payment = null will be called
     * only once for each user for First user invoice
     */
    public function processPayment(Invoice $invoice, InvoicePayment $payment = null)
    {
        $aff = $this->getDi()->modules->loadGet('aff')->getAffiliate($invoice, $payment);
        if (!$aff) return;

        $user = $invoice->getUser();
        if (!$user->aff_id) {
            $user->aff_id = $aff->pk();
            $user->aff_added = sqlTime('now');
            $user->data()->set('aff-source', 'invoice-' . $invoice->pk());
            $user->save();
        }

        // try to load other tier affiliate
        $aff_tier = $aff;
        $aff_tiers = [];
        $aff_tiers_exists = [$aff->pk()];
        for ($tier=1; $tier<=$this->getMaxTier(); $tier++) {
            if (!$aff_tier->aff_id || ($aff_tier->pk() == $invoice->getUser()->pk()))
                break;

            $aff_tier = $this->getDi()->userTable->load($aff_tier->aff_id, false);
            if (!$aff_tier || //not exists
                !$aff_tier->is_affiliate || //not affiliate
                ($aff_tier->pk() == $invoice->getUser()->pk()) || //original user
                in_array($aff_tier->pk(), $aff_tiers_exists))  //already in chain
                break;

            $aff_tiers[$tier] = $aff_tier;
            $aff_tiers_exists[] = $aff_tier->pk();
        }

        $isFirst = !$payment || $payment->isFirst(); //to define price field
        $paymentNumber = is_null($payment) ? 0 : $invoice->getPaymentsCount();
        if (!$payment) {
            $tax = 0;
            $shipping = 0;
        } else {
            $tax = $this->getDi()->config->get('aff.commission_include_tax', false) ? 0 : doubleval($payment->tax);
            $shipping = $this->getDi()->config->get('aff.commission_include_shipping', false) ? 0 : doubleval($payment->shipping);
        }

        $amount = $payment ? ($payment->amount - $shipping - $tax) : 0;
        $date = $payment ? $payment->dattm : 'now';
        // now calculate commissions
        $items = is_null($payment) ? array_slice($invoice->getItems(), 0, 1) : $invoice->getItems();
        foreach ($items as $item)
        {

            //we do not calculate commission for free items in invoice
            $prefix = $isFirst ? 'first' : 'second';
            if (!is_null($payment) && !(float)$item->get("{$prefix}_total")) continue;

            $comm = $this->getDi()->affCommissionRecord;
            $comm->date  = sqlDate($date);
            $comm->record_type = AffCommission::COMMISSION;
            $comm->invoice_id = $invoice->invoice_id;
            $comm->invoice_item_id = $item->invoice_item_id;
            $comm->invoice_payment_id  = $payment ? $payment->pk() : null;
            $comm->receipt_id  = $payment ? $payment->receipt_id : null;
            $comm->product_id  = $item->item_id;
            $comm->is_first    =  $paymentNumber<=1;
            $comm->keyword_id = $invoice->keyword_id;

            $comm->_setPayment($payment);
            $comm->_setInvoice($invoice);
            $comm_tier = clone $comm;

            $rules = [];
            $topay_this = $topay = $this->calculate($invoice, $item, $aff, $paymentNumber, 0, $amount, $date, $rules);
            if ($topay>0)
            {
                $comm->aff_id = $aff->pk();
                $comm->amount = $topay;
                $comm->tier = 0;
                $comm->_setAff($aff);
                $comm->insert();

                $comm->setCommissionRules(array_map(function ($el) {return $el->pk();}, $rules));
            }

            foreach ($aff_tiers as $tier => $aff_tier) {
                $rules = [];
                $topay_this = $this->calculate($invoice, $item, $aff_tier, $paymentNumber, $tier, $topay_this, $date, $rules);
                if ($topay_this>0)
                {
                    $comm_this = clone $comm_tier;
                    $comm_this->aff_id = $aff_tier->pk();
                    $comm_this->amount = $topay_this;
                    $comm_this->tier = $tier;
                    $comm_this->_setAff($aff_tier);
                    $comm_this->insert();
                    $comm_this->setCommissionRules(array_map(function ($el) {return $el->pk();}, $rules));
                }
            }
        }
    }

    /**
     * Process refund to rollback existing commissions
     */
    public function processRefund(Invoice $invoice, InvoiceRefund $refund)
    {
        if ($refund->invoice_payment_id)
            $toRefund = $this->getDi()->affCommissionTable->findByInvoicePaymentId($refund->invoice_payment_id);
        else
            $toRefund = $this->getDi()->affCommissionTable->findLastRecordsByInvoiceId($refund->invoice_id);
        foreach ($toRefund as $affCommission) {
            $this->getDi()->affCommissionTable->void($affCommission, $refund->dattm);
        }
    }

    public function hasCustomRules()
    {
        return $this->_db->selectCell("SELECT COUNT(*) FROM $this->_table
            WHERE `type` NOT IN (?a)",
            [AffCommissionRule::TYPE_GLOBAL,]);
    }
}