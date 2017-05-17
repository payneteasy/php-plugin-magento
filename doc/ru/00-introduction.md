# Plugin for Magento 1.x for pay by PaynetEasy

## Доступная функциональность

Данный  плагин позволяет производить оплату с помощью [merchant PaynetEasy API](http://wiki.payneteasy.com/index.php/PnE:Merchant_API). На текущий момент реализованы следующие платежные методы:
- [x] [Sale Transactions](http://wiki.payneteasy.com/index.php/PnE:Sale_Transactions)
- [ ] [Preauth/Capture Transactions](http://wiki.payneteasy.com/index.php/PnE:Preauth/Capture_Transactions)
- [ ] [Transfer Transactions](http://wiki.payneteasy.com/index.php/PnE:Transfer_Transactions)
- [ ] [Return Transactions](http://wiki.payneteasy.com/index.php/PnE:Return_Transactions)
- [ ] [Recurrent Transactions](http://wiki.payneteasy.com/index.php/PnE:Recurrent_Transactions)
- [x] [Payment Form Integration](http://wiki.payneteasy.com/index.php/PnE:Payment_Form_integration)
- [ ] [Buy Now Button integration](http://wiki.payneteasy.com/index.php/PnE:Buy_Now_Button_integration)
- [ ] [eCheck integration](http://wiki.payneteasy.com/index.php/PnE:eCheck_integration)
- [ ] [Western Union Integration](http://wiki.payneteasy.com/index.php/PnE:Western_Union_Integration)
- [ ] [Bitcoin Integration](http://wiki.payneteasy.com/index.php/PnE:Bitcoin_integration)
- [ ] [Loan Integration](http://wiki.payneteasy.com/index.php/PnE:Loan_integration)
- [ ] [Qiwi Integration](http://wiki.payneteasy.com/index.php/PnE:Qiwi_integration)
- [ ] [Merchant Callbacks](http://wiki.payneteasy.com/index.php/PnE:Merchant_Callbacks)

## Системные требования

* PHP 5.3 - 5.5
* [Расширение curl](http://php.net/manual/en/book.curl.php)
* [Magento](http://www.magentocommerce.com/download) 1.x (плагин тестировался с версией 1.7)

## <a name="get_package"></a> Получение пакета с плагином

### Самостоятельная сборка пакета
1. [Установите composer](http://getcomposer.org/doc/00-intro.md), если его еще нет
2. Клонируйте репозиторий с плагином: `composer create-project payneteasy/php-plugin-magento --stability=dev --prefer-dist`
3. Перейдите в папку плагина: `cd php-plugin-magento`
4. Упакуйте плагин в архив: `composer archive --format=zip`

## Установка плагина

1. [Получите пакет с плагином](#get_package)
2. Распакуйте пакет в корневую папку Magento

## Настройка плагина

1. Перейдите в панель администрирования Magento
2. Перейдите в раздел редактирования настроек системы (стрелка #1)

    ![go to configuration](../img/go_to_configuration.png)
3. Перейдите в раздел редактирования настроек методов оплаты (стрелка #1)

    ![go to payment methods](../img/go_to_payment_methods.png)
4. Настройте плагин
    1. Заполните форму с настройками плагина
    2. Сохраните настройки плагина (стрелка #1)

    ![edit config](../img/edit_config.png)

## Удаление плагина

Для удаления плагина необходимо удалить некоторые папки и файлы. Пути к ним даны относительно корневой папки Magento. Список файлов и папок для удаления:

* `app/code/local/PaynetEasy/`
* `app/design/frontend/base/default/template/paynet/`
* `app/etc/modules/PaynetEasy_Paynet.xml`
* `lib/composer/`
* `lib/payneteasy/`
* `lib/autoload.php`