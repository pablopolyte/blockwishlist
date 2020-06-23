<?php
/**
 * 2007-2020 PrestaShop and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\Module\BlockWishList\Search;

use Db;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use WishList;

/**
 * Responsible of getting products for specific wishlist.
 */
class WishListProductSearchProvider implements ProductSearchProviderInterface
{
    /**
     * @var Db
     */
    private $db;

    /**
     * @var WishList
     */
    private $wishList;

    /**
     * @param Db $db
     * @param WishList $wishList
     */
    public function __construct(Db $db, WishList $wishList)
    {
        $this->db = $db;
        $this->wishList = $wishList;
    }

    /**
     * @param ProductSearchContext $context
     * @param ProductSearchQuery $query
     *
     * @return ProductSearchResult
     */
    public function runQuery(
        ProductSearchContext $context,
        ProductSearchQuery $query
    ) {
        $context = \Context::getContext();
        $idLang = $context->language->id;
        $groups = \FrontController::getCurrentCustomerGroups();
        $sqlGroups = count($groups) ? 'IN (' . implode(',', $groups) . ')' : '=' . (int) \Group::getCurrent()->id;
        $activeCategory = true;
        $active = true;
        $front = true;
        if (!in_array($context->controller->controller_type, ['front', 'modulefront'])) {
            $front = false;
        }
        $p = 0;
        $n = 10;
        if ($p < 1) {
            $p = 1;
        }

        if (empty($orderBy) || $orderBy == 'position') {
            $orderBy = 'name';
        }

        if (empty($orderWay)) {
            $orderWay = 'ASC';
        }

        if ($orderBy == 'price') {
            $alias = 'product_shop.';
        } elseif ($orderBy == 'name') {
            $alias = 'pl.';
        } elseif ($orderBy == 'manufacturer_name') {
            $orderBy = 'name';
            $alias = 'm.';
        } elseif ($orderBy == 'quantity') {
            $alias = 'stock.';
        } else {
            $alias = 'p.';
        }

        // @todo Complete SQL Query
        // $querySearch = new \DbQuery();
        // $querySearch->select('p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity'
        // . (\Combination::isFeatureActive() ? ', product_attribute_shop.minimal_quantity AS product_attribute_minimal_quantity, IFNULL(product_attribute_shop.`id_product_attribute`,0) id_product_attribute' : '') . '
        // , pl.`description`, pl.`description_short`, pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`,
        // pl.`meta_title`, pl.`name`, pl.`available_now`, pl.`available_later`, image_shop.`id_image` id_image, il.`legend`, m.`name` AS manufacturer_name,
        //     DATEDIFF(
        //         product_shop.`date_add`,
        //         DATE_SUB(
        //             "' . date('Y-m-d') . ' 00:00:00",
        //             INTERVAL ' . (\Validate::isUnsignedInt(\Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? \Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20) . ' DAY
        //         )
        //     ) > 0 AS new'); // @todo Set fields used to render Product, example \ManufacturerCore::getProducts()
        // $querySearch->from('wishlist_product', 'wp');

        // $querySearch->leftJoin('product', 'p', 'p.`id_product` = wp.`id_product`');
        // $querySearch->join(\Shop::addSqlAssociation('product', 'p'));

        // $querySearch->innerJoin();
        // $querySearch->where('id_wishlist = ' . (int) $this->wishList->id);
        // $querySearch->limit(); // @todo use ProductSearchQuery to get pagination...

        $sql = 'SELECT p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity'
            . (\Combination::isFeatureActive() ? ', product_attribute_shop.minimal_quantity AS product_attribute_minimal_quantity, IFNULL(product_attribute_shop.`id_product_attribute`,0) id_product_attribute' : '') . '
            , pl.`description`, pl.`description_short`, pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`,
            pl.`meta_title`, pl.`name`, pl.`available_now`, pl.`available_later`, image_shop.`id_image` id_image, il.`legend`, m.`name` AS manufacturer_name,
                DATEDIFF(
                    product_shop.`date_add`,
                    DATE_SUB(
                        "' . date('Y-m-d') . ' 00:00:00",
                        INTERVAL ' . (\Validate::isUnsignedInt(\Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? \Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20) . ' DAY
                    )
                ) > 0 AS new'
            . ' FROM `' . _DB_PREFIX_ . 'product` p
            ' . \Shop::addSqlAssociation('product', 'p') .
            (\Combination::isFeatureActive() ? 'LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop
                        ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int) $context->shop->id . ')' : '') . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON (p.`id_product` = pl.`id_product` AND pl.`id_lang` = ' . (int) $idLang . \Shop::addSqlRestrictionOnLang('pl') . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` image_shop
                    ON (image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop=' . (int) $context->shop->id . ')
            LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il
                ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int) $idLang . ')
            LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m
                ON (m.`id_manufacturer` = p.`id_manufacturer`)
            LEFT JOIN `' . _DB_PREFIX_ . 'wishlist_product` wp
                ON (wp.`id_product` = p.`id_product`)
            ' . \Product::sqlStock('p', 0);

        if (\Group::isFeatureActive() || $activeCategory) {
            $sql .= 'JOIN `' . _DB_PREFIX_ . 'category_product` cp ON (p.id_product = cp.id_product)';
            if (\Group::isFeatureActive()) {
                $sql .= 'JOIN `' . _DB_PREFIX_ . 'category_group` cg ON (cp.`id_category` = cg.`id_category` AND cg.`id_group` ' . $sqlGroups . ')';
            }
            if ($activeCategory) {
                $sql .= 'JOIN `' . _DB_PREFIX_ . 'category` ca ON cp.`id_category` = ca.`id_category` AND ca.`active` = 1';
            }
        }

        $sql .= '
                WHERE wp.`id_wishlist` = ' . (int) $this->wishList->id . '
                ' . ($active ? ' AND product_shop.`active` = 1' : '') . '
                ' . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '') . '
                GROUP BY p.id_product
                ORDER BY ' . $alias . '`' . bqSQL($orderBy) . '` ' . pSQL($orderWay) . '
                LIMIT ' . (((int) $p - 1) * (int) $n) . ',' . (int) $n;

        $products = $this->db->executeS($sql);

        if (empty($products)) {
            $products = [];
        }
        // @todo Complete SQL Query count
        // $querySearch = new \DbQuery();
        // $querySearch->select('COUNT(*)');
        // $querySearch->from();
        // $querySearch->innerJoin();
        // $querySearch->where('id_wishlist = ' . (int) $this->wishList->id);
        // $querySearch->where(); // @todo use ProductSearchContext to get Language identifier etc...
        // @todo No use pagination here, we want count all results

        $sql = '
            SELECT COUNT(p.`id_product`)
            FROM `' . _DB_PREFIX_ . 'product` p
            ' . \Shop::addSqlAssociation('product', 'p') . '

            LEFT JOIN `' . _DB_PREFIX_ . 'wishlist_product` wp
            ON (wp.`id_product` = p.`id_product`)

            WHERE wp.`id_wishlist` = ' . (int) $this->wishList->id
            . ($active ? ' AND product_shop.`active` = 1' : '') . '
            ' . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '') . '
            AND EXISTS (
                SELECT 1
                FROM `' . _DB_PREFIX_ . 'category_group` cg
                LEFT JOIN `' . _DB_PREFIX_ . 'category_product` cp ON (cp.`id_category` = cg.`id_category`)' .
                ($activeCategory ? ' INNER JOIN `' . _DB_PREFIX_ . 'category` ca ON cp.`id_category` = ca.`id_category` AND ca.`active` = 1' : '') . '
                WHERE p.`id_product` = cp.`id_product` AND cg.`id_group` ' . $sqlGroups . '
                )';

        $count = (int) $this->db->getValue($sql);

        dump($count);
        die;
        $result = new ProductSearchResult();
        $result->setProducts($products);
        $result->setTotalProductsCount($count);

        return $result;
    }
}
