<?php
/**
 * This file is part of the Money library
 *
 * Copyright (c) 2011-2013 Mathias Verraes
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Money;

class Money
{
    const ROUND_HALF_UP = PHP_ROUND_HALF_UP;
    const ROUND_HALF_DOWN = PHP_ROUND_HALF_DOWN;
    const ROUND_HALF_EVEN = PHP_ROUND_HALF_EVEN;
    const ROUND_HALF_ODD = PHP_ROUND_HALF_ODD;

	/** @var Money[] */
	static $instances = array();

    /**
     * @var int
     */
    private $amount;

    /** @var \Money\Currency */
    private $currency;

    /**
     * Create a Money instance
     * @param  integer $amount    Amount, expressed in the smallest units of $currency (eg cents)
     * @param  \Money\Currency|string $currency as Obj or isoString
     * @param bool $parseAmountAsMoneyString amount ist unit (not subuit) and in moneyformat (maybe , as dec_mark)
     * @throws \Money\InvalidArgumentException
	 */
	public function __construct($amount, $currency=null, $parseAmountAsMoneyString=false)
    {
        if (!$parseAmountAsMoneyString && !is_int($amount) && !ctype_digit($amount)) { // only numbers(int) - as string or int type
            throw new InvalidArgumentException("The first parameter of Money must be an integer. It's the amount, expressed in the smallest units of currency (eg cents)");
        }
	    $this->currency = Currency::getInstance($currency);
	    $this->setAmount($amount, $parseAmountAsMoneyString);
    }

    /**
	 * return new instance of MoneyObj
	 * @param null $currency
	 * @param int $amount
     * @param bool $parseAmountAsMoneyString amount ist unit (not subuit) and in moneyformat (maybe , as dec_mark)
     * @return Money
	 */
	public static function newInstance($currency=null, $amount=0, $parseAmountAsMoneyString = false) {
        return new static($amount, $currency, $parseAmountAsMoneyString);
	}

    /**
	 * return saved instance of a MoneyObj with given currency - for single use to reduce memory usage in loops
     * WARNING: NOT SAFE FOR EXTERNAL USAGE - ONLY INTERN OBJ CACHE
	 * @param null $currency
	 * @return Money
	 */
	public static function getInstance($currency=null) {
		// get isocode from currency, direct or default
		$iso_code = $currency instanceof Currency ? $currency->getIsostring() : ($currency ? : Currency::getDefaultCurrency());
		if (!array_key_exists($iso_code, static::$instances)) {
			static::$instances[$iso_code] = new static(0, $currency);
		}
		return static::$instances[$iso_code];
	}

    /**
     * Convenience factory method for a Money object
     * @example $fiveDollar = Money::USD(500);
     * @param string $method
     * @param array $arguments
     * @return \Money\Money
     */
    public static function __callStatic($method, $arguments)
    {
        return new Money($arguments[0], new Currency($method));
    }

	/**
	 * change the amount
	 * @param int $amount amount in subunit
	 * @param bool $parseAmountAsMoneyString amount ist unit (not subuit) and in moneyformat (maybe , as dec_mark)
	 * @return $this
	 */
	public function setAmount($amount, $parseAmountAsMoneyString = false) {
		$this->amount = $parseAmountAsMoneyString ? self::parseMoneyString($amount, $this->getCurrency()) : $amount;
		return $this;
    }

    /**
     * @param \Money\Money $other
     * @return bool
     */
    public function isSameCurrency(Money $other)
    {
        return $this->currency->equals($other->currency);
    }

    /**
     * @throws \Money\InvalidArgumentException
     */
    private function assertSameCurrency(Money $other)
    {
        if (!$this->isSameCurrency($other)) {
            throw new InvalidArgumentException('Different currencies');
        }
    }

    /**
     * @param \Money\Money $other
     * @return bool
     */
    public function equals(Money $other)
    {
        return
            $this->isSameCurrency($other)
            && $this->amount == $other->amount;
    }

    /**
     * @param \Money\Money $other
     * @return int
     */
    public function compare(Money $other)
    {
        $this->assertSameCurrency($other);
        if ($this->amount < $other->amount) {
            return -1;
        } elseif ($this->amount == $other->amount) {
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * @param \Money\Money $other
     * @return bool
     */
    public function greaterThan(Money $other)
    {
        return 1 == $this->compare($other);
    }

    /**
     * @param \Money\Money $other
     * @return bool
     */
    public function lessThan(Money $other)
    {
        return -1 == $this->compare($other);
    }

    /**
     * @deprecated Use getAmount() instead
     * @return int
     */
    public function getUnits()
    {
        return $this->amount;
    }

	/**
	 * @param bool $asUnit - default as (int)cent for internals, with true for exernals(string) (formfields etc) as $, EUR etc.
	 * if subunit == 0 -> strip decimal places
	 * @param array $params
	 *  force_decimal -> add ,00 if cents are empty ("0"-quantitiy from decimalPlaces)
	 * @return int|string
	 */
	public function getAmount($asUnit=false, $params=array())
    {
	    if (!$asUnit)
		    return $this->amount;

	    // without decimal if == 0 if params dont force
	    $subunit = (string)floor($this->amount) % $this->currency->getSubunitToUnit();
	    if (($subunit == 0 && (!isset($params['force_decimal']) || !$params['force_decimal'])) || $this->currency->getDecimalPlaces() == 0)
		    return (string)floor($this->amount) / $this->currency->getSubunitToUnit();

	    return (string)number_format($this->amount / $this->currency->getSubunitToUnit(), $this->currency->getDecimalPlaces(), $this->currency->getDecimalMark(), '');
    }

    /**
     * @return \Money\Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param \Money\Money $addend
     *@return \Money\Money 
     */
    public function add(Money $addend)
    {
        $this->assertSameCurrency($addend);

        return new self($this->amount + $addend->amount, $this->currency);
    }

    /**
     * @param \Money\Money $subtrahend
     * @return \Money\Money
     */
    public function subtract(Money $subtrahend)
    {
        $this->assertSameCurrency($subtrahend);

        return new self($this->amount - $subtrahend->amount, $this->currency);
    }

    /**
     * @throws \Money\InvalidArgumentException
     */
    private function assertOperand($operand)
    {
        if (!is_int($operand) && !is_float($operand)) {
            throw new InvalidArgumentException('Operand should be an integer or a float');
        }
    }

    /**
     * @throws \Money\InvalidArgumentException
     */
    private function assertRoundingMode($rounding_mode)
    {
        if (!in_array($rounding_mode, array(self::ROUND_HALF_DOWN, self::ROUND_HALF_EVEN, self::ROUND_HALF_ODD, self::ROUND_HALF_UP))) {
            throw new InvalidArgumentException('Rounding mode should be Money::ROUND_HALF_DOWN | Money::ROUND_HALF_EVEN | Money::ROUND_HALF_ODD | Money::ROUND_HALF_UP');
        }
    }

    /**
     * @param $multiplier
     * @param int $rounding_mode
     * @return \Money\Money
     */
    public function multiply($multiplier, $rounding_mode = self::ROUND_HALF_UP)
    {
        $this->assertOperand($multiplier);
        $this->assertRoundingMode($rounding_mode);

        $product = (int) round($this->amount * $multiplier, 0, $rounding_mode);

        return new Money($product, $this->currency);
    }

    /**
     * @param $divisor
     * @param int $rounding_mode
     * @return \Money\Money
     */
    public function divide($divisor, $rounding_mode = self::ROUND_HALF_UP)
    {
        $this->assertOperand($divisor);
        $this->assertRoundingMode($rounding_mode);

        $quotient = (int) round($this->amount / $divisor, 0, $rounding_mode);

        return new Money($quotient, $this->currency);
    }

    /**
     * Allocate the money according to a list of ratio's
     * @param array $ratios List of ratio's
     * @return \Money\Money
     */
    public function allocate(array $ratios)
    {
        $remainder = $this->amount;
        $results = array();
        $total = array_sum($ratios);

        foreach ($ratios as $ratio) {
            $share = (int) floor($this->amount * $ratio / $total);
            $results[] = new Money($share, $this->currency);
            $remainder -= $share;
        }
        for ($i = 0; $remainder > 0; $i++) {
            $results[$i]->amount++;
            $remainder--;
        }

        return $results;
    }

    /** @return bool */
    public function isZero()
    {
        return $this->amount === 0;
    }

    /** @return bool */
    public function isPositive()
    {
        return $this->amount > 0;
    }

    /** @return bool */
    public function isNegative()
    {
        return $this->amount < 0;
    }

	/**
	 * @see Money::parseMoneyString()
	 */
	public static function stringToUnits($string, $currency=null) {
		return self::parseMoneyString($string, $currency);
	}

	/**
	 * parse moneyformated string and returns amount in subunit
	 * @param $string
	 * @param null $currency
	 * @return int subunit from Money-string
	 * @throws InvalidArgumentException
	 */
	public static function parseMoneyString($string, $currency = null) {
		$currency = Currency::getInstance($currency);
		$t = str_replace('.', '\.', $currency->getThousandsSeparator());
		$d = str_replace('.', '\.', $currency->getDecimalMark());
		if (!preg_match("/^([-+])?(?:0|([1-9]\d{0,2})(?:$t?(\d{3}))*)(?:$d(\d+))?$/", $string, $matches)) {
			throw new InvalidArgumentException(sprintf('The string "%s" could not be parsed as money', $string));
		}
		$units = (float)(@$matches[1] . @$matches[2] . @$matches[3] . '.' . @$matches[4]) * 100;
		return (int)round($units);
	}

	/**
	 * returns the formatted Money Amount
	 * @param array $params
	 *  display_free => shows 'free' if amount == 0
	 *  html => display html formatted
	 *  symbol => user_defined symbol
	 *  no_cents => show no cents
	 *  no_cents_if_zero => show no cents if ==00
	 *  thousands_separator => overwrite the currency thousands_separator
	 *  decimal_mark => overwrite the currency decimal_mark
	 *  with_currency => append currency ISOCODE
	 *  no_blank_separator => remove the blank separator between amount and symbol/currency
	 * @return mixed|string
	 */
	public function format($params = array()) {
		// spearator between amount andsymbol/currency
		$sep = (array_key_exists('no_blank_separator', $params) && $params['no_blank_separator']) ? '' : ' ';
		// show 'free'
		if ($this->amount === 0) {
			if (isset($params['display_free']) && is_string($params['display_free']))
				return $params['display_free'];
			elseif (isset($params['display_free']) && $params['display_free'])
				return "free";
		}

		// html symbol
		$symbolValue = $this->currency->getSymbol(isset($params['html']) && $params['html']);

		// userdefined symbol
		if (isset($params['symbol']) && $params['symbol'] !== true) {
			if (!$params['symbol'])
				$symbolValue = '';
			else
				$symbolValue = $params['symbol'];
		}

		// show no_cents
		if (isset($params['no_cents']) && $params['no_cents'] === true) {
			$formatted = (string)floor($this->getAmount(true));
		// no_cents if ==00 -> default behjaviour from getAmount(true)
		} elseif (isset($params['no_cents_if_zero']) && $params['no_cents_if_zero'] === true && $this->amount % $this->currency->getSubunitToUnit() == 0) {
			$formatted = (string)$this->getAmount(true);
		} else {
			$formatted = $this->getAmount(true, array('force_decimal' => true));
		}

		// warp span arrount amount if html
		if (isset($params['html']) && $params['html']) {
			$formatted = '<span class="amount">' . $formatted . '</span>';
		}

		if (isset($params['symbol_position']))
			$symbolPosition = $params['symbol_position'];
		else
			$symbolPosition = $this->currency->getSymbolPosition(true);

		// wrap span arround symbol if html
		if (isset($params['html']) && $params['html']) {
			$symbolValue = '<span class="symbol">' . $symbolValue . '</span>';
		}

		// combine symbol and formatted amount
		if (isset($symbolValue) && !empty($symbolValue)) {
			$formatted = $symbolPosition === 'before'
					? "$symbolValue$sep$formatted"
					: "$formatted$sep$symbolValue";
		}

		if (isset($params['decimal_mark']) && $params['decimal_mark'] && $params['decimal_mark'] !== $this->currency->getDecimalMark()) {
			$tmp = 1; /* Needs to be pass by ref */
			$formatted = str_replace($this->currency->getDecimalMark(), $params['decimal_mark'], $formatted, $tmp);
		}

		$thousandsSeparatorValue = $this->getCurrency()->getThousandsSeparator();
		if (isset($params['thousands_separator'])) {
			if ($params['thousands_separator'] === false || $params['thousands_separator'] === null)
				$thousandsSeparatorValue = '';
			elseif ($params['thousands_separator'])
				$thousandsSeparatorValue = $params['thousands_separator'];
		}

		$formatted = preg_replace('/(\d)(?=(?:\d{3})+(?:[^\d]|$))/', '\1' . $thousandsSeparatorValue, $formatted);

		if (isset($params['with_currency']) && $params['with_currency']) {

			if (isset($params['html']) && $params['html'])
				$formatted .= $sep.'<span class="currency">'. $this->currency->__toString(). '</span>';
			else
				$formatted .= $sep.$this->currency->__toString();
		}

		return $formatted;
	}


	/**
	 * build string represantiation of the amount without formatting and currency sign
	 * @return string
	 */
	public function __toString() {
		try {
			return $this->getAmount(true);
		} catch (\Exception $e) {
			return 'ERR';
		}
	}

}
