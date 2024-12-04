<?php

interface ITradeStation
{
  /**
   * Initializes the TradeStation with the specified trading mode.
   *
   * @param string $which Specifies the trading mode, either 'buy' or 'sell'.
   */
  public function __construct(string $which);

  /**
   * Installs caching for TradeStation with predefined cache keys and methods.
   *
   * @return void
   */
  public function installCache(): void;

  /**
   * Cancels an order by the specified order ID.
   *
   * @param int $order_id The ID of the order to cancel.
   * @return mixed Response from the API for the cancellation request.
   */
  public function cancel(int $order_id);

  /**
   * Retrieves the cash balance of the account.
   *
   * @return float|null The account's cash balance, or null on error.
   * @throws InvalidAccountID If the account ID is invalid.
   */
  public function getBalance(): ?float;

  /**
   * Retrieves the account equity balance.
   *
   * @return float|null The equity balance, or null on error.
   * @throws InvalidAccountID If the account ID is invalid.
   */
  public function getEquity(): ?float;

  /**
   * Retrieves the account's buying power.
   *
   * @return float|null The buying power, or null on error.
   * @throws InvalidAccountID If the account ID is invalid.
   */
  public function getBuyingPower(): ?float;

  /**
   * Retrieves detailed information about a specific stock.
   *
   * @param string $symbol The stock symbol.
   * @return array|null Stock information, or null if unavailable.
   */
  public function getStock(string $symbol): ?array;

  /**
   * Estimates the stock price for a given order type and action.
   *
   * @param string $stock_symbol The stock symbol.
   * @param string $order_type The type of order (e.g., 'Market').
   * @param string $trade_action The trade action (e.g., 'BUY', 'SELL').
   * @return float|null Estimated price, or null on error.
   */
  public function getStockEstimatedPrice(string $stock_symbol, string $order_type, string $trade_action): ?float;

  /**
   * Places a new order with specified parameters.
   *
   * @param array $stock Stock details including symbol and quantity.
   * @param string $order_type Type of order (e.g., 'Limit', 'Market').
   * @param string $trade_action Action for the trade (e.g., 'BUY', 'SELL').
   * @param array $args can include Quantity of stocks to order, $percent Optional percentage adjustment for price or trailStop.
   * @return int|null The order ID if successful, or null on error.
   * @throws InsufficientMoney If buying power is insufficient.
   */
  public function placeOrder(array $stock, string $order_type, string $trade_action, array $args = []): ?int;

  /**
   * Places a One-Cancels-the-Other (OCO) order group.
   *
   * @param array $stock Stock details for the OCO order.
   * @param array $operations Array of order operations in the OCO group.
   * @return mixed API response for the OCO order group request.
   */
  public function placeOCO(array $stock, array $operations);

  /**
   * Edit an existing order with the provided data.
   *
   * @param array $stock Stock details for the OCO order.
   * @param int $order_id The unique identifier of the order to be edited.
   * @param array $data Additional data for editing the order, must contain 'order_type' and 'trade_action'.
   * @return int|null Returns the order ID if successful, null otherwise.
   * @throws InvalidArguments If required data fields are missing.
   */
  public function editOrder(array $stock, int $order_id, array $data): ?int;

  /**
   * Get the status of an existing order.
   *
   * @param int $order_id The unique identifier of the order.
   * @return string|null Status of the order, or null if not found.
   */
  public function getOrderStatus(int $order_id);

  /**
   * Retrieve full order details.
   *
   * @param int $order_id The unique identifier of the order.
   * @return array|null Returns order details or null if not found.
   */
  public function getOrder(int $order_id);

  /**
   * Get the executed quantity for a specific order.
   *
   * @param int $order_id The unique identifier of the order.
   * @return int Executed quantity of the order, or 0 if not found.
   */
  public function getExecQuantity(int $order_id): int;

  /**
   * Close an open position based on provided trade information.
   *
   * @param array $trade Trade details, which may include limit, stop, and trail order IDs.
   * @return void
   */
  public function closePosition(array $trade): void;

  /**
   * Get a real-time data streamer for a specific stock.
   *
   * @param string stock symbol.
   * @return ArteCurl Streamer instance for real-time stock data.
   */
  public function getStreamer(string $symbol): ArteCurl;
}
