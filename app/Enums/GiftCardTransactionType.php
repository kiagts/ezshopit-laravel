<?php
namespace App\Enums;
enum GiftCardTransactionType:string { case Issue='issue'; case Redeem='redeem'; case Refund='refund'; case Adjustment='adjustment'; }
