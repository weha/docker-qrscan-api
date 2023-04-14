import cv2
import sys

filename = sys.argv[1]

# read the QRCODE image
#in case if QR code is not black/white it is better to convert it into grayscale
img = cv2.imread(filename, 0)# Zero means grayscale
img_origin = cv2.imread(filename)

# initialize the cv2 QRCode detector
detector = cv2.QRCodeDetector()

# detect and decode
data, bbox, straight_qrcode = detector.detectAndDecode(img)

# if there is a QR code
if bbox is not None:
    print(f"{data}")
