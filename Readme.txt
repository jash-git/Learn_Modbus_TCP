Modbus GOOGLE: Modbus tcp

01.https://zh.wikipedia.org/wiki/Modbus
	-通訊和裝置
		有一個節點是master節點，其他使用Modbus協定參與通訊的節點是slave節點。
		每一個slave裝置都有一個唯一的位址。
		只有被指定為主節點的節點可以啟動一個命令（在乙太網上，任何一個裝置都能傳送一個Modbus命令，但是通常也只有一個主節點裝置啟動指令）。
		一個ModBus命令包含了打算執行的裝置的Modbus位址。所有裝置都會收到命令，但只有指定位置的裝置會執行及回應指令
	-結論:多Server[slave]，單Client[master]
	
02.http://www.modbus.org/docs/Modbus_Messaging_Implementation_Guide_V1_0b.pdf
	-1.2 CLIENT / SERVER MODEL
	The MODBUS messaging service provides a Client/Server communication between devices connected on an Ethernet TCP/IP network.
	This client / server model is based on four type of messages:
	• MODBUS Request,
	• MODBUS Confirmation,
	• MODBUS Indication,
	• MODBUS Response
	
03.http://www.icpdas.com.tw/products/PAC/i-8000/modbus_c.htm
	-C# 通用庫
	
04.http://godspeedlee.myweb.hinet.net/php_modbus/
	01.pdf-http://www.advantech.tw/products/a67f7853-013a-4b50-9b20-01798c56b090/adam-6015/mod_9c835a28-5c91-49fc-9de1-ec7f1dd3a82d
	02.pdf-http://support.advantech.com/Support/SearchResult.aspx?keyword=ADAM-6015&searchtabs=BIOS,Certificate,Datasheet,Driver,Firmware,Manual,Online%20Training,Software%20Utility,Utility,FAQ,Installation,Software%20API,Software%20API%20Manual,3D%20Model&select_tab=Datasheet
	03.rar-http://support.advantech.com/Support/DownloadSRDetail_New.aspx?SR_ID=1-1WMBN3&Doc_Source=Download
	04.zip-http://support.advantech.com/Support/DownloadSRDetail_New.aspx?SR_ID=1-NOV2O&Doc_Source=Download
	05.pdf-http://support.advantech.com/Support/DownloadSRDetail_New.aspx?SR_ID=1-95WMW&Doc_Source=Download
	
	