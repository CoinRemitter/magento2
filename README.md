CoinRemitter Plugin For Magento
===

Coinremitter is a [crypto payment processor](http://coinremitter.com). Accept Bitcoin, Bitcoin Cash, Litecoin, Dogecoin, Dash, Tron, Binance ,Tether USD ERC20,Tether USD TRC20 etc.View all supported currency [here](http://coinremitter.com/supported-currencies).


**What Is Crypto Payment Gateway?**

There are ample of Crypto Coins available on crypto payment gateways. You can pick one of them and create a wallet of that coins and purchase things from individual’s websites who are accepting payment in crypto coins though. Regardless, All these websites have their own API in order to accept payment from buyers.

Apart from centralized currencies this option creates a traffic for sellers who are willing to do payments in crypto coins. In contrast, doing a payment in crypto coins offer buyers a great market  reputation and has left a foremost impact on sellers and it will also benefit to buyers & sellers if they choose **Coinremitter: Crypto Payment Gateway** as their payment method in doing a business in crypto coins.



Prerequisites
---
* For the Integration process with Coinremitter, users must require to have  Magento version 2.x
* If you don't have a Coinremitter account then please consider making it one.  [Create Account ](https://coinremitter.com/signup)

Installation Of Plugin:
---
1. Download CoinRemitter magento plugin (zip file).
2. Login to your server and unzip the file and rename it to ‘Checkout’.
3. Navigate to Magento installation root directory. Go to `app/code/` (If code/ does not exist, create it).Inside `app/code/` , create a folder named **Coinremitter**.Move the Checkout folder into `app/code/CoinRemitter/`.
4. Run following commands in terminal to install Coinremitter Plugin
	* php bin/magento setup:upgrade 
	* php bin/magento setup:di:compile
	* php bin/magento setup:static-content:deploy -f
	* php bin/magento cache:flush

5. **Done!** The plugin is now installed. Follow the plugin's documentation to activate it.

Plugin Configuration
---
* On the dashboard, you will see the menu on the left side, where you can see the **Store** option, click on it. Sidebar will open and click on **configuration**, afterwards a new page will appear, click on **sales** and then click on **Payment methods**.
* On that page, Scroll down and you will find Configuration option of **Coinremitter CryptoPayment**. 
* On the box of service you will see multiple options to fill in.
* You will find the first option Enable. Select it to **Yes**.
* In the second option you can create your own **Title** if you need. It will display to user on checkout page
* In the **Description** tab you can add some notes to tell your customer some meaningful things before the customer makes any step during checkout. 
* Set **Invoice Expiry Time**. It is in minutes. So if you set value 30 then the created invoice will expire after 30 minutes.
* In the last tab of **Order status** you can select one of your own status about what you want to show to customers when they successfully made out payment. 
(select appropriately because it will appear once payment gets done)

Create Wallet
---
Go to the menu on your left side and you’ll see the **Coinremitter Checkout** option. Another sidebar will open as soon as you click on it. **Wallet** option will appear there and click on it also.

* Now you are on the **Wallet - coinremitter** page.
* You’ll find the **Add Wallet** button on the top of the page. Click on it.
* After clicking on the add wallet a new page will appear where you’ll see multiple options like **API key, Password**.
* Now go to coinremitter website and login to your account and get your API key from there. If you find any trouble to get your api then [**click here**](https://blog.coinremitter.com/how-to-get-api-key-and-password-of-coinremitter-wallet/) to get the idea.
* Get back to the Magento coinremitter page and select one of your coins. Paste API key in the box and fill your Password in the box.
* Don’t forget to add the exchange rate multiplier.
* The default price multiplier is set to 1. For instance, if you set it to 1.10, then prices for cryptocurrencies will be increased by 10%, and you can set it to 0.95 in this text box for a 5% discount.
* Setting the minimum invoice limit is necessary, The generated invoice won’t be less than the minimum invoice limit.Set this amount in default fiat currency of your website.
* Click on the **Save wallet** on top of the page.

![Coinremitter-Plugin-Save-wallet](https://coinremitter.com/assets/img/screenshots/magento2/add-wallet.png)

* Congratulations! You have now successfully created your wallet.

> **Note:**

> - You can also see your other wallet list and can Edit/Delete your wallet by clicking on the **select** option which you can find in the **Action** column in wallet list table. And you also have to add the URL in the **Webhook URL field of your Coinremitter wallet's General Settings.** URL can be Seen below in the image.

![Coinremitter-Plugin-wallet-list](https://coinremitter.com/assets/img/screenshots/magento2/wallets.png)

![Coinremitter-Plugin-wallet-edit-view](https://coinremitter.com/assets/img/screenshots/magento2/edit-wallet.png)

You have successfully activated coinremitter plugin.

How to make payment
---
* Once a customer creates an order and fills all the mandatory details, the system will take them on the payment page.
* You will see **Pay Using Cryptocurrency** option. Click on it.
* Select one of your coin wallets from you want to pay for your product and click on **place order**.

![Coinremitter-Plugin-make-payment-page](https://coinremitter.com/assets/img/screenshots/magento2/checkout.png)

* On the very next moment the system will automatically generate an **Invoice** which will appear on your screen.

![Coinremitter-Plugin-inovice-page](https://coinremitter.com/assets/img/screenshots/magento2/invoice.png)

* Copy **Payment address** from generated invoice and pay exact amount from your personal wallet. Once you transfer to this address, it requires 3 confirmations to mark order as paid. Then it will automatically redirect to the success page once payment is confirmed on blockchain.

![Coinremitter-Plugin-thank-you-page](https://coinremitter.com/assets/img/screenshots/magento2/thankyou.png) 

* Congratulations! You have now successfully paid for your product. 

Check order details
---
* Go to your **admin panel** menu and click on **Sales**, sidebar opens and click on **order**.
* Once you reach the **order** page you will see your multiple orders list. Select one of these orders. Make sure that order is paid using coinremitter payment option.
* Click on the **view** from one order and the new tab will open. 
* On the left side menu you will see the **Payment Details (coinremitter)** tab. Click on it.
* You will see the details about payment details.

![Coinremitter-Plugin-payment-detail](https://coinremitter.com/assets/img/screenshots/magento2/payment-detail.png) 
