# komtet-kassa-joomshopping
Компонент КОМТЕТ Кассы для JoomShopping 4.x

Данное решение позволяет подключить Ваш интернет-магазин к облачному сервису КОМТЕТ Касса с целью соответствия требованиям 54-ФЗ для регистрации расчетов с использованием электронного средства платежа в сети Интернет.

### Версия
1.0

### Возможности плагина
  - автоматическая фискализация платежей.

### Описание работы
Плагин реагирует событие когда клиент совершает оплату через один из подключенных модулей приема платежей, например, PayPal или Robokassa и направляет данные о заказе в систему КОМТЕТ Касса.

Как только данные по заказу появляются в системе КОМТЕТ Касса, формируется чек, который записывается на фискальный накопитель кассового аппарата и он же отправляется в ОФД (Оператор Фискальных Данных). Если указано в настройках, аппарат может распечатать бланк чека.

Важно! 54-ФЗ обязует выдать электронный чек клиенту, для того чтобы электронный чек был выслан клиенту на электронную почту необходимо сделать обязательным поле email на форме оформления заказа.

### Установка
Для установки плагина нужно выполнить слудующие действия:
1. Войти в админ панель Joomla и выбрать пункт "Менеджер расширений" в меню "Расширения"
2. На открывшейся странице "Менеджер расширений: Установка" вверху нажать на "Файл пакета" либо на кнопку слева от него.
3. Выбрать .zip архив плагина. Когда вы нашли необходимый архив плагина, нужно нажать кнопку "Открыть", а затем кликнуть "Загрузить и установить".
4. Когда плагин будет загружен и установлен, Вы увидите сообщение об успешной установке.



### Настройка плагина

Прежде чем приступить к настройке плагина, вам потребуется зарегистрироваться в [личном кабинете на сайте КОМТЕТ Касса](https://kassa.komtet.ru/signup).

В настройках плагина необходимо указать:
1. ID Магазина. В личном кабинете на сайте КОМТЕТ Касса зайдите в меню «Магазины» (слева в выпадающем меню "Фискализация"), далее выберете нужный магазин и зайдите в его настройки, там вы и найдете необходимое значение (ShopId).
2. Secret Магазина. Аналогично предыдущему (Secret).
2. ID Очереди. В личном кабинете на сайте КОМТЕТ Касса зайдите в меню «Кассы» (слева в выпадающем меню "Фискализация"), далее найдите нужный магазин и слева от его названия вы найдете четырехзначное число (ID очереди).
4. Включить или отключить печать бумажного чека.
5. Указать систему налогообложения вашей компании. Данные о системе налогообложения будут использованы при формировании чека.
6. Указать через запятую ID способов оплаты, для которых выполнять фискализацию. Их можно посмтреть в списке способов оплаты в вашем JoomShopping.

License
----

MIT
