<?php
/**
 * bank_account 格式統一工具
 *
 * 資料庫中的 bank_account 一律存成「幣別 + 空白 + 帳號」，例如 "USD 01753032142"。
 * 因為同一組帳號會跨幣別共用（01753032142 同時是 USD 與 EUR 帳戶），
 * 少了幣別就無法分辨是哪一個帳戶，所以 Received / Applied / Transferred
 * 各種寫入都必須經過這裡格式化。
 */

if (!function_exists('formatBankAccount')) {
    /**
     * 依該筆資料的幣別補上（或修正）bank_account 的幣別前綴
     *
     * @param string|null $bank_account 帳號，可帶或不帶幣別前綴
     * @param string|null $currency     該筆資料的幣別（TWD / USD / EUR ...）
     * @return string 「幣別 帳號」格式的字串
     */
    function formatBankAccount($bank_account, $currency)
    {
        $bank_account = trim((string) $bank_account);
        $currency = strtoupper(trim((string) $currency));

        // 沒有帳號時不要生出只剩幣別的字串
        if ($bank_account === '') {
            return '';
        }

        // 幣別不明時維持原值，避免亂補
        if ($currency === '') {
            return $bank_account;
        }

        // 去掉既有前綴（可能是舊資料的其他幣別，或大小寫、空白不一致）
        $account_only = preg_replace('/^[A-Za-z]{3}\s+/', '', $bank_account);

        return $currency . ' ' . $account_only;
    }
}

if (!function_exists('stripBankAccountCurrency')) {
    /**
     * 取出純帳號（去掉幣別前綴），用於跟舊格式資料比對
     *
     * @param string|null $bank_account
     * @return string
     */
    function stripBankAccountCurrency($bank_account)
    {
        $bank_account = trim((string) $bank_account);

        return preg_replace('/^[A-Za-z]{3}\s+/', '', $bank_account);
    }
}
